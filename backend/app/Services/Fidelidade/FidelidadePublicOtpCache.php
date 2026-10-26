<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * Cache OTP da consulta pública de fidelidade na vitrine.
 */
final class FidelidadePublicOtpCache
{
    public static function invalidarTelefone(int $unidadeFid, string $telNorm): void
    {
        $tel = FidelidadeNormalizer::telefone($telNorm);
        if (strlen($tel) < 10 || ! Schema::hasTable('dlv_loja_config')) {
            return;
        }

        $vitrines = DB::table('dlv_loja_config')
            ->where('ativo', 1)
            ->where(function ($q) use ($unidadeFid) {
                $q->where('unidade_id', $unidadeFid);
                if (Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
                    $q->orWhere('unidade_fidelidade_id', $unidadeFid);
                }
            })
            ->pluck('unidade_id');

        foreach ($vitrines as $uid) {
            self::limparUnidade((int) $uid, $tel);
        }

        self::limparUnidade($unidadeFid, $tel);
    }

    public static function limparUnidade(int $unidadeVitrineId, string $telNorm): void
    {
        foreach ([
            'sas_fid_otp:'.$unidadeVitrineId.':'.$telNorm,
            'sas_fid_otp_falhas:'.$unidadeVitrineId.':'.$telNorm,
            'sas_fid_otp_cad:'.$unidadeVitrineId.':'.$telNorm,
            'sas_fid_otp_falhas_cad:'.$unidadeVitrineId.':'.$telNorm,
        ] as $key) {
            Cache::forget($key);
        }

        RateLimiter::clear('sas-fid-otp:'.$unidadeVitrineId.':'.$telNorm);
    }
}
