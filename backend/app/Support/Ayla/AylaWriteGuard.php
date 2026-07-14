<?php

namespace App\Support\Ayla;

use App\Models\AylaUsuarioAutorizado;
use App\Support\SasIa\SasIaContext;
use Illuminate\Support\Facades\DB;

/**
 * Gate de escrita controlada da Ayla (confirmação + pode_executar_acoes).
 */
final class AylaWriteGuard
{
    /**
     * @return array{ok: true, usuario: object, ctx: SasIaContext}|array{ok: false, message: string, code: string, http: int}
     */
    public static function autorizarEscrita(?int $userId, ?string $telegramUserId = null, string $moduloMenu = 'reservaMesa'): array
    {
        if (AylaSettings::somenteLeitura()) {
            return [
                'ok' => false,
                'message' => 'A integração está em modo somente leitura. Desative AYLA_READ_ONLY e libere pode_executar_acoes.',
                'code' => 'READ_ONLY',
                'http' => 403,
            ];
        }

        if ($userId === null || $userId < 1) {
            return [
                'ok' => false,
                'message' => 'Usuário SAS obrigatório para ações de escrita (header X-Usuario-Id).',
                'code' => 'INVALID_USER',
                'http' => 401,
            ];
        }

        $usuario = DB::table('usuarios')->where('id', $userId)->where('ativo', 1)->first();
        if (! $usuario) {
            return [
                'ok' => false,
                'message' => 'Usuário inválido ou inativo.',
                'code' => 'INVALID_USER',
                'http' => 401,
            ];
        }

        $ctx = new SasIaContext($usuario);
        $temMenu = $ctx->isAdmin()
            || $ctx->temModulo('reservaMesa')
            || $ctx->temModulo('historicoReservas')
            || $ctx->temModulo($moduloMenu);

        if (! $temMenu) {
            return [
                'ok' => false,
                'message' => 'Sem permissão de menu para reservas.',
                'code' => 'PERMISSION_DENIED',
                'http' => 403,
            ];
        }

        $telegramUserId = $telegramUserId !== null ? trim($telegramUserId) : '';

        if ($telegramUserId !== '') {
            $vinculo = AylaUsuarioAutorizado::query()
                ->where('telegram_user_id', $telegramUserId)
                ->where('usuario_id', $userId)
                ->where('status', 'ativo')
                ->first();

            if (! $vinculo) {
                return [
                    'ok' => false,
                    'message' => 'Telegram não vinculado a este usuário SAS.',
                    'code' => 'PERMISSION_DENIED',
                    'http' => 403,
                ];
            }

            if (! $ctx->isAdmin() && ! $vinculo->pode_executar_acoes) {
                return [
                    'ok' => false,
                    'message' => 'Usuário sem permissão para executar ações (pode_executar_acoes).',
                    'code' => 'WRITE_NOT_ALLOWED',
                    'http' => 403,
                ];
            }

            // ADMIN sempre pode (se vínculo ativo); GERENTE/outros precisam da flag.
            if ($ctx->isAdmin()) {
                return ['ok' => true, 'usuario' => $usuario, 'ctx' => $ctx];
            }

            if (! $vinculo->pode_executar_acoes) {
                return [
                    'ok' => false,
                    'message' => 'Usuário sem permissão para executar ações (pode_executar_acoes).',
                    'code' => 'WRITE_NOT_ALLOWED',
                    'http' => 403,
                ];
            }

            return ['ok' => true, 'usuario' => $usuario, 'ctx' => $ctx];
        }

        // Sem Telegram: vínculo Ayla ativo com escrita, ou ADMIN.
        $vinculo = AylaUsuarioAutorizado::query()
            ->where('usuario_id', $userId)
            ->where('status', 'ativo')
            ->first();

        if ($ctx->isAdmin()) {
            return ['ok' => true, 'usuario' => $usuario, 'ctx' => $ctx];
        }

        if (! $vinculo || ! $vinculo->pode_executar_acoes) {
            return [
                'ok' => false,
                'message' => 'Usuário sem permissão para executar ações (pode_executar_acoes).',
                'code' => 'WRITE_NOT_ALLOWED',
                'http' => 403,
            ];
        }

        return ['ok' => true, 'usuario' => $usuario, 'ctx' => $ctx];
    }

    public static function telegramDoRequest(\Illuminate\Http\Request $request): ?string
    {
        foreach (['X-Telegram-User-Id', 'X-Ayla-Sender-Id'] as $h) {
            $v = $request->header($h);
            if ($v !== null && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        $body = $request->input('telegram_user_id');
        if ($body !== null && trim((string) $body) !== '') {
            return trim((string) $body);
        }

        return null;
    }
}
