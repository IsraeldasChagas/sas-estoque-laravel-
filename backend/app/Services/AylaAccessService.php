<?php

namespace App\Services;

use App\Models\AylaUsuarioAutorizado;
use App\Support\Ayla\AylaSettings;
use App\Support\SasIa\SasIaContext;
use Illuminate\Support\Facades\DB;

/**
 * Autorização de acesso da Ayla a partir do identificador de Telegram.
 *
 * Regra central de segurança: o Telegram ID nunca concede acesso sozinho.
 * O acesso é sempre vinculado a um usuário SAS existente e ativo, e a
 * permissão efetiva é a INTERSEÇÃO entre:
 *   permissoes_menu do usuário SAS  ∩  módulos permitidos na Ayla  ∩  unidades permitidas na Ayla.
 * A Ayla nunca concede mais do que o usuário já possui no SAS.
 */
class AylaAccessService
{
    /**
     * @return array<string, mixed>
     */
    public function autorizarTelegram(string $telegramUserId, ?string $username = null): array
    {
        $telegramUserId = trim($telegramUserId);
        if ($telegramUserId === '') {
            return $this->negar('Identificador de Telegram ausente.');
        }

        $vinculo = AylaUsuarioAutorizado::where('telegram_user_id', $telegramUserId)->first();
        if (! $vinculo) {
            return $this->negar('Telegram não vinculado a nenhum usuário autorizado.');
        }

        if ($vinculo->status !== 'ativo') {
            $motivos = [
                'pendente' => 'Acesso ainda não foi ativado pelo administrador.',
                'bloqueado' => 'Acesso bloqueado pelo administrador.',
                'revogado' => 'Acesso revogado pelo administrador.',
            ];

            return $this->negar($motivos[$vinculo->status] ?? 'Acesso não autorizado.');
        }

        $usuario = DB::table('usuarios')->where('id', $vinculo->usuario_id)->first();
        if (! $usuario) {
            return $this->negar('Usuário SAS não encontrado.');
        }
        if (! (int) ($usuario->ativo ?? 0)) {
            return $this->negar('Usuário SAS inativo.');
        }

        $ctx = new SasIaContext($usuario);

        // Módulos: interseção entre o que a Ayla permite e o que o usuário tem no SAS.
        $aylaModulos = is_array($vinculo->modulos_permitidos) && $vinculo->modulos_permitidos !== []
            ? $vinculo->modulos_permitidos
            : AylaSettings::modulosLiberados();

        $modulosEfetivos = array_values(array_filter(
            $aylaModulos,
            fn ($m) => $ctx->isAdmin() || $ctx->temModulo((string) $m)
        ));

        // Unidades: as definidas na Ayla; vazio = todas que o usuário já enxerga no SAS.
        $unidades = is_array($vinculo->unidades_permitidas)
            ? array_values(array_filter(array_map('intval', $vinculo->unidades_permitidas), fn ($v) => $v > 0))
            : [];
        if ($unidades === [] && $ctx->unidadeEfetiva()) {
            $unidades = [$ctx->unidadeEfetiva()];
        }

        // Registrar último acesso (sem quebrar em caso de erro).
        try {
            $vinculo->update(['ultimo_acesso_em' => now()]);
        } catch (\Throwable $e) {
            // silencioso
        }

        return [
            'autorizado' => true,
            'motivo' => null,
            'usuario_id' => (int) $usuario->id,
            'nome' => (string) ($usuario->nome ?? ''),
            'perfil' => $ctx->perfil(),
            'cargo' => $vinculo->cargo,
            'unidades_permitidas' => $unidades,
            'modulos_permitidos' => $modulosEfetivos,
            'pode_usar_texto' => (bool) $vinculo->pode_usar_texto,
            'pode_usar_audio' => (bool) $vinculo->pode_usar_audio,
            'pode_consultar_dados' => (bool) $vinculo->pode_consultar_dados,
            // Nesta versão a API é somente leitura: ações de escrita nunca são liberadas.
            'pode_executar_acoes' => AylaSettings::somenteLeitura() ? false : (bool) $vinculo->pode_executar_acoes,
        ];
    }

    /** @return array<string, mixed> */
    private function negar(string $motivo): array
    {
        return [
            'autorizado' => false,
            'motivo' => $motivo,
            'usuario_id' => null,
            'nome' => null,
            'perfil' => null,
            'cargo' => null,
            'unidades_permitidas' => [],
            'modulos_permitidos' => [],
            'pode_usar_texto' => false,
            'pode_usar_audio' => false,
            'pode_consultar_dados' => false,
            'pode_executar_acoes' => false,
        ];
    }
}
