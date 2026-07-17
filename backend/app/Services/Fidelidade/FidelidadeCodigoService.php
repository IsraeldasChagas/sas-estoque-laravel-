<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;

final class FidelidadeCodigoService
{
    public static function gerar(): string
    {
        for ($i = 0; $i < 50; $i++) {
            $code = 'SAS-'.date('Y').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! DB::table('fid_contas')->where('codigo_fidelidade', $code)->exists()) {
                return $code;
            }
        }

        return 'SAS-'.date('Y').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
}
