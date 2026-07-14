<?php

namespace App\Services\Ayla;

use App\Models\AylaAcaoPendente;
use App\Support\Ayla\AylaSettings;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestra preparar → confirmar → executar ações de reservas (escrita controlada).
 */
class AylaAcaoPendenteService
{
    public const ACOES_RESERVAS = [
        'criar',
        'criar_reserva',
        'atualizar',
        'atualizar_reserva',
        'alterar_mesa',
        'confirmar',
        'confirmar_reserva',
        'registrar_chegada',
        'finalizar',
        'finalizar_reserva',
        'cancelar',
        'cancelar_reserva',
        'criar_mesa_emergencial',
        'ajustar_capacidade_mesa',
        'preparar_composicao_mesas',
        'criar_mesa_emergencial_e_reservar',
        'criar_alerta_operacional',
    ];

    public function __construct(private AylaReservasService $reservas) {}

    /**
     * Valida intenção, verifica disponibilidade/conflito e grava ação pendente.
     * Não altera a reserva.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $meta  usuario_id, telegram_user_id, canal
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function preparar(array $input, array $meta): array
    {
        if (! Schema::hasTable('ayla_acoes_pendentes')) {
            return [
                'ok' => false,
                'code' => 'MIGRATION_REQUIRED',
                'message' => 'Tabela ayla_acoes_pendentes ausente. Execute a migration.',
            ];
        }

        $acao = strtolower(trim((string) ($input['acao'] ?? '')));
        if (! in_array($acao, self::ACOES_RESERVAS, true)) {
            return [
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Ação inválida. Permitidas: '.implode(', ', self::ACOES_RESERVAS).'.',
            ];
        }

        $dados = is_array($input['dados'] ?? null) ? $input['dados'] : [];
        $preview = $this->reservas->prepararPreview($acao, $dados);

        if (! ($preview['ok'] ?? false)) {
            return [
                'ok' => false,
                'code' => $preview['code'] ?? 'VALIDATION_ERROR',
                'message' => $preview['message'] ?? 'Não foi possível preparar a ação.',
                'data' => $preview['data'] ?? [],
            ];
        }

        // Duplicidade: exige forcar_duplicidade no payload.
        if (! empty($preview['data']['possivel_duplicidade']) && empty($dados['forcar_duplicidade'])) {
            return [
                'ok' => true,
                'data' => [
                    'requer_confirmacao_duplicidade' => true,
                    'mensagem' => 'Aparentemente já existe uma reserva semelhante. Deseja continuar mesmo assim?',
                    'similares' => $preview['data']['similares'] ?? [],
                    'resumo_proposto' => $preview['data']['resumo'] ?? null,
                    'payload_sugerido' => array_merge($dados, ['forcar_duplicidade' => true]),
                    'instrucao' => 'Peça confirmação ao usuário e chame preparar novamente com dados.forcar_duplicidade=true.',
                ],
            ];
        }

        $payloadNormalizado = $preview['data']['payload'] ?? $dados;
        $resumo = (string) ($preview['data']['resumo_texto'] ?? $preview['data']['resumo'] ?? $acao);

        // Cancela outras pendentes do mesmo usuário/módulo (evita confirmação ambígua).
        $this->cancelarPendentesAnteriores(
            (int) ($meta['usuario_id'] ?? 0),
            isset($meta['telegram_user_id']) ? (string) $meta['telegram_user_id'] : null
        );

        $registro = AylaAcaoPendente::create([
            'usuario_id' => $meta['usuario_id'] ?? null,
            'telegram_user_id' => $meta['telegram_user_id'] ?? null,
            'canal' => $meta['canal'] ?? 'api',
            'modulo' => 'reservas',
            'acao' => $acao,
            'payload' => $payloadNormalizado,
            'resumo' => is_string($resumo) ? $resumo : json_encode($resumo, JSON_UNESCAPED_UNICODE),
            'status' => AylaAcaoPendente::STATUS_PENDENTE,
            'expira_em' => now()->addMinutes(AylaAcaoPendente::EXPIRACAO_MINUTOS),
        ]);

        return [
            'ok' => true,
            'data' => [
                'acao_id' => (int) $registro->id,
                'acao' => $acao,
                'status' => $registro->status,
                'expira_em' => $registro->expira_em?->toIso8601String(),
                'resumo' => $preview['data']['resumo'] ?? null,
                'resumo_texto' => $registro->resumo,
                'perguntar' => 'Deseja confirmar esta ação?',
                'instrucao' => 'Aguarde resposta afirmativa do usuário e então chame reservas_confirmar_acao com este acao_id.',
                'alternativas' => $preview['data']['alternativas'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function confirmar(int $acaoId, array $meta): array
    {
        if (! Schema::hasTable('ayla_acoes_pendentes')) {
            return [
                'ok' => false,
                'code' => 'MIGRATION_REQUIRED',
                'message' => 'Tabela ayla_acoes_pendentes ausente.',
            ];
        }

        $registro = AylaAcaoPendente::find($acaoId);
        if (! $registro || $registro->modulo !== 'reservas') {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Ação pendente não encontrada.'];
        }

        if ($registro->status === AylaAcaoPendente::STATUS_EXECUTADA) {
            return [
                'ok' => false,
                'code' => 'ALREADY_EXECUTED',
                'message' => 'Esta ação já foi executada. Não é possível executar duas vezes.',
                'data' => ['resultado' => $registro->resultado],
            ];
        }

        if ($registro->status === AylaAcaoPendente::STATUS_CANCELADA) {
            return ['ok' => false, 'code' => 'CANCELLED', 'message' => 'Esta ação foi cancelada.'];
        }

        if ($registro->status === AylaAcaoPendente::STATUS_EXPIRADA || $registro->expirou()) {
            if ($registro->status !== AylaAcaoPendente::STATUS_EXPIRADA) {
                $registro->update(['status' => AylaAcaoPendente::STATUS_EXPIRADA]);
            }

            return [
                'ok' => false,
                'code' => 'EXPIRED',
                'message' => 'Ação expirada (limite de 10 minutos). Prepare novamente.',
            ];
        }

        if ($registro->status !== AylaAcaoPendente::STATUS_PENDENTE) {
            return [
                'ok' => false,
                'code' => 'INVALID_STATE',
                'message' => 'Ação não está pendente de confirmação.',
            ];
        }

        // Somente o mesmo usuário SAS / Telegram pode confirmar.
        $uid = (int) ($meta['usuario_id'] ?? 0);
        if ($registro->usuario_id !== null && $uid > 0 && (int) $registro->usuario_id !== $uid) {
            return [
                'ok' => false,
                'code' => 'PERMISSION_DENIED',
                'message' => 'Outro usuário não pode confirmar esta ação.',
            ];
        }

        $tg = isset($meta['telegram_user_id']) ? trim((string) $meta['telegram_user_id']) : '';
        if ($registro->telegram_user_id !== null && $registro->telegram_user_id !== '' && $tg !== '') {
            if ($registro->telegram_user_id !== $tg) {
                return [
                    'ok' => false,
                    'code' => 'PERMISSION_DENIED',
                    'message' => 'Telegram ID diferente do que preparou a ação.',
                ];
            }
        }

        $registro->update([
            'status' => AylaAcaoPendente::STATUS_CONFIRMADA,
            'confirmado_em' => now(),
        ]);

        $resultado = $this->reservas->executarAcaoConfirmada(
            (string) $registro->acao,
            is_array($registro->payload) ? $registro->payload : [],
            $uid > 0 ? $uid : null
        );

        if (! ($resultado['ok'] ?? false)) {
            $registro->update([
                'status' => AylaAcaoPendente::STATUS_ERRO,
                'resultado' => $resultado,
                'executado_em' => now(),
            ]);

            return [
                'ok' => false,
                'code' => $resultado['code'] ?? 'EXECUTION_ERROR',
                'message' => $resultado['message'] ?? 'Falha ao executar a ação.',
                'data' => $resultado['data'] ?? [],
            ];
        }

        $registro->update([
            'status' => AylaAcaoPendente::STATUS_EXECUTADA,
            'resultado' => $resultado['data'] ?? [],
            'executado_em' => now(),
        ]);

        return [
            'ok' => true,
            'data' => [
                'acao_id' => (int) $registro->id,
                'acao' => $registro->acao,
                'status' => AylaAcaoPendente::STATUS_EXECUTADA,
                'resultado' => $resultado['data'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function cancelar(int $acaoId, array $meta): array
    {
        if (! Schema::hasTable('ayla_acoes_pendentes')) {
            return [
                'ok' => false,
                'code' => 'MIGRATION_REQUIRED',
                'message' => 'Tabela ayla_acoes_pendentes ausente.',
            ];
        }

        $registro = AylaAcaoPendente::find($acaoId);
        if (! $registro || $registro->modulo !== 'reservas') {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Ação pendente não encontrada.'];
        }

        if ($registro->status === AylaAcaoPendente::STATUS_EXECUTADA) {
            return [
                'ok' => false,
                'code' => 'ALREADY_EXECUTED',
                'message' => 'Ação já executada; não é possível cancelar.',
            ];
        }

        $uid = (int) ($meta['usuario_id'] ?? 0);
        if ($registro->usuario_id !== null && $uid > 0 && (int) $registro->usuario_id !== $uid) {
            return [
                'ok' => false,
                'code' => 'PERMISSION_DENIED',
                'message' => 'Outro usuário não pode cancelar esta ação.',
            ];
        }

        $registro->update(['status' => AylaAcaoPendente::STATUS_CANCELADA]);

        return [
            'ok' => true,
            'data' => [
                'acao_id' => (int) $registro->id,
                'status' => AylaAcaoPendente::STATUS_CANCELADA,
                'message' => 'Ação pendente cancelada. Nenhuma reserva foi alterada.',
            ],
        ];
    }

    private function cancelarPendentesAnteriores(int $usuarioId, ?string $telegramUserId): void
    {
        if ($usuarioId < 1 && ($telegramUserId === null || $telegramUserId === '')) {
            return;
        }

        $q = AylaAcaoPendente::query()
            ->where('modulo', 'reservas')
            ->where('status', AylaAcaoPendente::STATUS_PENDENTE);

        if ($usuarioId > 0) {
            $q->where('usuario_id', $usuarioId);
        }
        if ($telegramUserId) {
            $q->where(function ($qq) use ($telegramUserId) {
                $qq->whereNull('telegram_user_id')
                    ->orWhere('telegram_user_id', $telegramUserId);
            });
        }

        $q->update(['status' => AylaAcaoPendente::STATUS_CANCELADA, 'updated_at' => now()]);
    }
}
