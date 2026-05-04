<?php

namespace App\Support\Rh;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RhAuditoria
{
    public static function registrar(
        string $evento,
        ?string $referenciaTipo,
        ?int $referenciaId,
        ?int $usuarioId,
        ?string $ip,
        ?array $detalhes
    ): void {
        if (! Schema::hasTable('rh_auditoria')) {
            return;
        }

        $row = [
            'evento' => $evento,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'usuario_id' => $usuarioId,
            'ip' => $ip ? substr($ip, 0, 64) : null,
        ];
        if ($detalhes !== null && $detalhes !== []) {
            $row['detalhes'] = $detalhes;
        } else {
            $row['detalhes'] = null;
        }

        DB::table('rh_auditoria')->insert($row);
    }
}
