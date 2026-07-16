<?php

namespace App\Services\Ayla;

use App\Models\AylaConvite;
use App\Models\AylaUsuarioAutorizado;
use App\Support\Ayla\AylaSettings;
use App\Support\Ayla\AylaTelefone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AylaConviteService
{
    public function __construct(
        private readonly AylaTelegramSyncService $sync,
    ) {}

    public function botUsername(): string
    {
        $fromEnv = ltrim(trim((string) config('ayla.telegram_bot_username', '')), '@');
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return ltrim(trim((string) (AylaSettings::paraPainel()['telegram_bot_username'] ?? '')), '@');
    }

    public function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    /** @return array{success: bool, message?: string, data?: array<string, mixed>} */
    public function gerar(AylaUsuarioAutorizado $acesso, int $criadoPor, ?string $telefone = null): array
    {
        $telefoneNorm = AylaTelefone::normalizar($telefone ?? $acesso->telefone_telegram);
        if (! AylaTelefone::validar($telefoneNorm)) {
            return ['success' => false, 'message' => 'Informe um telefone brasileiro válido com DDD para gerar o convite.'];
        }

        $acesso->telefone_telegram = $telefoneNorm;
        $acesso->save();

        $this->cancelarPendentes($acesso, $criadoPor, 'renovacao');

        $token = bin2hex(random_bytes(32));
        $horas = max(1, (int) config('ayla.convite_validade_horas', 24));

        $convite = AylaConvite::query()->create([
            'ayla_usuario_autorizado_id' => $acesso->id,
            'usuario_id' => $acesso->usuario_id,
            'token_hash' => $this->hashToken($token),
            'status' => AylaConvite::STATUS_PENDENTE,
            'expira_em' => now()->addHours($horas),
            'telefone_telegram' => $telefoneNorm,
            'criado_por' => $criadoPor,
        ]);

        $url = $this->montarUrl($token);

        return [
            'success' => true,
            'data' => [
                'convite_id' => $convite->id,
                'convite_url' => $url,
                'convite_token' => $token,
                'expira_em' => $convite->expira_em->toIso8601String(),
                'status' => $convite->status,
                'telefone_telegram' => AylaTelefone::formatar($telefoneNorm),
                'telefone_telegram_mascarado' => AylaTelefone::mascarar($telefoneNorm),
            ],
        ];
    }

    public function renovar(AylaUsuarioAutorizado $acesso, int $criadoPor): array
    {
        return $this->gerar($acesso, $criadoPor, $acesso->telefone_telegram);
    }

    public function cancelarPendentes(AylaUsuarioAutorizado $acesso, ?int $porUsuario = null, string $motivo = 'cancelado'): int
    {
        return AylaConvite::query()
            ->where('ayla_usuario_autorizado_id', $acesso->id)
            ->where('status', AylaConvite::STATUS_PENDENTE)
            ->update([
                'status' => AylaConvite::STATUS_CANCELADO,
                'cancelado_em' => now(),
            ]);
    }

    /** @return array<string, mixed> */
    public function statusParaPainel(AylaUsuarioAutorizado $acesso, bool $admin = true): array
    {
        $convite = AylaConvite::query()
            ->where('ayla_usuario_autorizado_id', $acesso->id)
            ->orderByDesc('id')
            ->first();

        $conectado = $acesso->temTelegramConectado();
        $estado = 'nao_conectado';
        if ($acesso->status === 'bloqueado') {
            $estado = 'bloqueado';
        } elseif ($conectado && $acesso->telegram_sync_status === 'erro') {
            $estado = 'sync_erro';
        } elseif ($conectado) {
            $estado = 'conectado';
        } elseif ($convite && $convite->status === AylaConvite::STATUS_PENDENTE && ! $convite->expirou()) {
            $estado = 'convite_pendente';
        }

        $telefone = $admin
            ? AylaTelefone::formatar($acesso->telefone_telegram)
            : AylaTelefone::mascarar($acesso->telefone_telegram);

        return [
            'estado' => $estado,
            'conectado' => $conectado,
            'telegram_user_id' => $admin ? $acesso->telegram_user_id : ($acesso->telegram_user_id ? '••••'.substr((string) $acesso->telegram_user_id, -4) : null),
            'telegram_username' => $acesso->telegram_username,
            'telegram_nome' => $acesso->telegram_nome,
            'telegram_vinculado_em' => $acesso->telegram_vinculado_em?->toIso8601String(),
            'telegram_sync_status' => $acesso->telegram_sync_status,
            'telegram_sync_erro' => $acesso->telegram_sync_erro,
            'telegram_sincronizado_em' => $acesso->telegram_sincronizado_em?->toIso8601String(),
            'telefone_telegram' => $telefone,
            'telefone_telegram_mascarado' => AylaTelefone::mascarar($acesso->telefone_telegram),
            'convite' => $convite ? [
                'id' => $convite->id,
                'status' => $convite->expirou() && $convite->status === AylaConvite::STATUS_PENDENTE
                    ? AylaConvite::STATUS_EXPIRADO
                    : $convite->status,
                'expira_em' => $convite->expira_em?->toIso8601String(),
                'usado_em' => $convite->usado_em?->toIso8601String(),
                'cancelado_em' => $convite->cancelado_em?->toIso8601String(),
            ] : null,
            'bot_username' => $this->botUsername(),
        ];
    }

    /**
     * @param  array{telegram_user_id: string, telegram_username?: ?string, telegram_nome?: ?string}  $dados
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function vincularPorToken(string $tokenPlain, array $dados): array
    {
        $tokenPlain = trim($tokenPlain);
        $telegramId = trim((string) ($dados['telegram_user_id'] ?? ''));
        if ($tokenPlain === '' || $telegramId === '' || ! preg_match('/^[0-9]{3,32}$/', $telegramId)) {
            return ['success' => false, 'message' => 'Convite ou identificador do Telegram inválido.'];
        }

        $hash = $this->hashToken($tokenPlain);

        return DB::transaction(function () use ($hash, $telegramId, $dados) {
            $convite = AylaConvite::query()
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $convite) {
                return ['success' => false, 'message' => 'Convite inválido ou não encontrado.'];
            }

            if ($convite->status === AylaConvite::STATUS_USADO) {
                return ['success' => false, 'message' => 'Este convite já foi utilizado.'];
            }
            if ($convite->status === AylaConvite::STATUS_CANCELADO) {
                return ['success' => false, 'message' => 'Este convite foi cancelado.'];
            }
            if ($convite->expirou() || $convite->status === AylaConvite::STATUS_EXPIRADO) {
                $convite->update(['status' => AylaConvite::STATUS_EXPIRADO]);

                return ['success' => false, 'message' => 'Este convite expirou. Solicite um novo ao administrador.'];
            }

            $duplicado = AylaUsuarioAutorizado::query()
                ->where('telegram_user_id', $telegramId)
                ->where('status', 'ativo')
                ->where('id', '!=', $convite->ayla_usuario_autorizado_id)
                ->exists();
            if ($duplicado) {
                return ['success' => false, 'message' => 'Este Telegram já está vinculado a outro usuário ativo.'];
            }

            /** @var AylaUsuarioAutorizado|null $acesso */
            $acesso = AylaUsuarioAutorizado::query()->lockForUpdate()->find($convite->ayla_usuario_autorizado_id);
            if (! $acesso) {
                return ['success' => false, 'message' => 'Acesso não encontrado.'];
            }

            $username = isset($dados['telegram_username']) ? ltrim(trim((string) $dados['telegram_username']), '@') : null;
            $nome = trim((string) ($dados['telegram_nome'] ?? ''));

            $acesso->telegram_user_id = $telegramId;
            $acesso->telegram_username = $username ?: $acesso->telegram_username;
            $acesso->telegram_nome = $nome !== '' ? $nome : $acesso->telegram_nome;
            $acesso->telegram_vinculado_em = now();
            $acesso->status = 'ativo';
            $acesso->autorizado_em = $acesso->autorizado_em ?? now();
            $acesso->save();

            $convite->update([
                'status' => AylaConvite::STATUS_USADO,
                'usado_em' => now(),
                'telegram_user_id' => $telegramId,
                'telegram_username' => $acesso->telegram_username,
                'telegram_nome' => $acesso->telegram_nome,
            ]);

            $this->cancelarPendentes($acesso);

            $sync = $this->sync->adicionarAllowlist($telegramId);
            if (! ($sync['success'] ?? false)) {
                $acesso->update([
                    'telegram_sync_status' => 'erro',
                    'telegram_sync_erro' => $sync['message'] ?? 'Falha ao sincronizar com a VPS.',
                ]);
            } else {
                $acesso->update([
                    'telegram_sync_status' => 'ok',
                    'telegram_sync_erro' => null,
                    'telegram_sincronizado_em' => now(),
                ]);
            }

            $usuario = DB::table('usuarios')->where('id', $acesso->usuario_id)->first();

            return [
                'success' => true,
                'message' => 'Telegram vinculado com sucesso.',
                'data' => [
                    'usuario_id' => (int) $acesso->usuario_id,
                    'nome' => (string) ($usuario->nome ?? ''),
                    'telegram_user_id' => $telegramId,
                    'status' => $acesso->status,
                    'telefone_telegram' => AylaTelefone::mascarar($acesso->telefone_telegram),
                    'allowlist_required' => true,
                    'sync_ok' => ($sync['success'] ?? false),
                ],
            ];
        });
    }

    public function montarUrl(string $token): string
    {
        $bot = $this->botUsername();
        if ($bot === '') {
            return 'https://t.me/?start='.$token;
        }

        return 'https://t.me/'.$bot.'?start='.$token;
    }

    /** Mensagem pronta para WhatsApp. */
    public function mensagemWhatsApp(AylaUsuarioAutorizado $acesso, string $conviteUrl, ?string $nomeDestino = null): string
    {
        $nome = $nomeDestino ?: (DB::table('usuarios')->where('id', $acesso->usuario_id)->value('nome') ?? 'colaborador(a)');
        $telefone = AylaTelefone::mascarar($acesso->telefone_telegram) ?? 'informado';
        $horas = (int) config('ayla.convite_validade_horas', 24);

        return "Olá, {$nome}.\n"
            ."Você recebeu acesso à Ayla, assistente do Grupo Sabor Paraense.\n"
            ."Este convite foi gerado para o Telegram utilizado no número {$telefone}.\n"
            ."Toque no link abaixo e depois em INICIAR no Telegram:\n"
            ."{$conviteUrl}\n"
            ."Este convite expira em {$horas} horas.";
    }
}
