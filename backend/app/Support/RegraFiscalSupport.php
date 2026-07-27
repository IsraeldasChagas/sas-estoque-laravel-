<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RegraFiscalSupport
{
    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('regras_fiscais');
    }

    /** @return object|null */
    public static function regraAplicavel(
        string $tributo,
        ?string $regime,
        string $tipoOperacao,
        ?string $dataRef = null
    ) {
        if (! self::moduloAtivo()) {
            return null;
        }
        $ref = $dataRef ?? now()->format('Y-m-d');
        $q = DB::table('regras_fiscais')
            ->where('ativo', true)
            ->where('tributo', $tributo)
            ->where('tipo_operacao', $tipoOperacao)
            ->where('vigencia_inicio', '<=', $ref)
            ->where(function ($w) use ($ref) {
                $w->whereNull('vigencia_fim')->orWhere('vigencia_fim', '>=', $ref);
            })
            ->orderByDesc('versao')
            ->orderByDesc('id');

        if ($regime) {
            $q->where(function ($w) use ($regime) {
                $w->whereNull('regime_tributario')->orWhere('regime_tributario', $regime);
            });
        }

        return $q->first();
    }

    public static function calcularEstimativa(object $regra, float $base): float
    {
        $cfg = json_decode($regra->configuracao_json ?? '{}', true);
        if (! is_array($cfg)) {
            return 0.0;
        }
        if (($cfg['tipo_calculo'] ?? '') === 'percentual_base') {
            $pct = (float) ($cfg['percentual_estimado'] ?? 0);

            return round(max(0, $base) * $pct, 2);
        }

        return 0.0;
    }

    public static function versaoAtual(): string
    {
        if (! self::moduloAtivo()) {
            return '0';
        }
        $max = DB::table('regras_fiscais')->max('versao');

        return 'regras-v' . (int) $max;
    }
}
