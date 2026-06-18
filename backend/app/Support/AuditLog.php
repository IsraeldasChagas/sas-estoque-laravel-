<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro central de auditoria (quem fez o quê e quando).
 * Usado em exclusões e alterações sensíveis de RH/Financeiro.
 */
final class AuditLog
{
    public static function registrar(
        ?int $usuarioId,
        string $acao,
        string $recurso,
        $recursoId = null,
        ?string $descricao = null,
        ?array $dadosExtras = null,
        ?Request $request = null
    ): void {
        if (! Schema::hasTable('audit_logs') || ! $usuarioId) {
            return;
        }

        DB::table('audit_logs')->insert([
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'recurso' => $recurso,
            'recurso_id' => $recursoId,
            'descricao' => $descricao,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'dados_extras' => $dadosExtras !== null && $dadosExtras !== []
                ? json_encode($dadosExtras, JSON_UNESCAPED_UNICODE)
                : null,
            'created_at' => now(),
        ]);
    }
}
