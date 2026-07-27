<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ApuracaoFiscalSupport
{
    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('apuracoes_fiscais');
    }

    /** @return array<string, mixed> */
    public static function calcular(int $empresaId, string $periodoInicio, string $periodoFim): array
    {
        if (! self::moduloAtivo()) {
            return ['ok' => false, 'error' => 'Módulo 7 não migrado.'];
        }

        $regime = DB::table('empresas')->where('id', $empresaId)->value('regime_tributario');
        $f = ['empresa_id' => $empresaId, 'data_ini' => $periodoInicio, 'data_fim' => $periodoFim];

        $debitosPorTributo = self::debitosVendaPeriodo($empresaId, $periodoInicio, $periodoFim);
        $creditosPorTributo = self::creditosPeriodo($empresaId, $periodoInicio, $periodoFim);
        $estornos = FiscalConsolidacaoSupport::listarEstornos($f, 500);
        $estornoTotal = 0.0;
        foreach ($estornos as $e) {
            $estornoTotal += (float) (is_array($e) ? ($e['valor_potencial'] ?? 0) : ($e->valor_potencial ?? 0));
        }

        $tributos = array_unique(array_merge(array_keys($debitosPorTributo), array_keys($creditosPorTributo), ['icms', 'pis', 'cofins']));
        $itens = [];
        $totalDebitos = 0.0;
        $totalCreditos = 0.0;
        $totalEstimado = 0.0;

        foreach ($tributos as $trib) {
            if ($trib === '') {
                continue;
            }
            $deb = (float) ($debitosPorTributo[$trib] ?? 0);
            $cred = (float) ($creditosPorTributo[$trib] ?? 0);
            if ($deb <= 0 && $cred <= 0) {
                $receita = FiscalConsolidacaoSupport::visaoGeral($f)['cards']['receita'] ?? 0;
                $regra = RegraFiscalSupport::regraAplicavel($trib, $regime, 'venda');
                if ($regra && $receita > 0) {
                    $deb = RegraFiscalSupport::calcularEstimativa($regra, (float) $receita);
                }
            }
            $est = $trib === 'icms' ? min($estornoTotal, $cred * 0.1) : 0;
            $estimado = max(0, $deb - $cred + $est);
            $regraId = RegraFiscalSupport::regraAplicavel($trib, $regime, 'venda')?->id;
            $itens[] = [
                'tributo' => $trib,
                'debitos' => round($deb, 2),
                'creditos' => round($cred, 2),
                'estornos' => round($est, 2),
                'ajustes' => 0,
                'valor_estimado' => round($estimado, 2),
                'regra_fiscal_id' => $regraId,
            ];
            $totalDebitos += $deb;
            $totalCreditos += $cred;
            $totalEstimado += $estimado;
        }

        if ($regime) {
            $regraReg = RegraFiscalSupport::regraAplicavel(
                match ($regime) {
                    'simples_nacional' => 'simples',
                    'lucro_presumido' => 'presumido',
                    'lucro_real' => 'irpj_csll',
                    default => 'presumido',
                },
                $regime,
                'venda'
            );
            if ($regraReg) {
                $receita = (float) FiscalConsolidacaoSupport::visaoGeral($f)['cards']['receita'];
                $val = RegraFiscalSupport::calcularEstimativa($regraReg, $receita);
                if ($val > 0) {
                    $itens[] = [
                        'tributo' => $regraReg->tributo,
                        'debitos' => $val,
                        'creditos' => 0,
                        'estornos' => 0,
                        'ajustes' => 0,
                        'valor_estimado' => $val,
                        'regra_fiscal_id' => $regraReg->id,
                    ];
                    $totalDebitos += $val;
                    $totalEstimado += $val;
                }
            }
        }

        $snapshot = [
            'cards' => FiscalConsolidacaoSupport::visaoGeral($f),
            'gerado_em' => now()->toIso8601String(),
        ];

        $apuracaoId = DB::table('apuracoes_fiscais')->insertGetId([
            'empresa_id' => $empresaId,
            'periodo_inicio' => $periodoInicio,
            'periodo_fim' => $periodoFim,
            'regime_tributario' => $regime,
            'status' => 'calculada',
            'total_debitos' => round($totalDebitos, 2),
            'total_creditos' => round($totalCreditos, 2),
            'total_estornos' => round($estornoTotal, 2),
            'total_estimado' => round($totalEstimado, 2),
            'regra_versao' => RegraFiscalSupport::versaoAtual(),
            'snapshot_json' => json_encode($snapshot),
            'calculado_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($itens as $row) {
            DB::table('apuracao_fiscal_itens')->insert(array_merge($row, [
                'apuracao_id' => $apuracaoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return ['ok' => true, 'apuracao_id' => $apuracaoId, 'totais' => [
            'debitos' => round($totalDebitos, 2),
            'creditos' => round($totalCreditos, 2),
            'estimado' => round($totalEstimado, 2),
        ]];
    }

    public static function validar(int $apuracaoId, ?int $usuarioId, ?array $valoresValidados = null): bool
    {
        if (! self::moduloAtivo()) {
            return false;
        }
        $ap = DB::table('apuracoes_fiscais')->where('id', $apuracaoId)->first();
        if (! $ap) {
            return false;
        }
        $totalVal = null;
        if ($valoresValidados) {
            foreach ($valoresValidados as $itemId => $valor) {
                DB::table('apuracao_fiscal_itens')->where('id', (int) $itemId)->where('apuracao_id', $apuracaoId)
                    ->update(['valor_validado' => $valor, 'updated_at' => now()]);
            }
            $totalVal = array_sum(array_map('floatval', $valoresValidados));
        }
        DB::table('apuracoes_fiscais')->where('id', $apuracaoId)->update([
            'status' => 'validada',
            'validado_em' => now(),
            'validado_por' => $usuarioId,
            'total_validado' => $totalVal ?? $ap->total_estimado,
            'updated_at' => now(),
        ]);

        return true;
    }

    /** @return array<string, float> */
    protected static function debitosVendaPeriodo(int $empresaId, string $ini, string $fim): array
    {
        if (! Schema::hasTable('tributos_venda') || ! Schema::hasTable('vendas')) {
            return [];
        }
        $rows = DB::table('tributos_venda')
            ->join('vendas', 'tributos_venda.venda_id', '=', 'vendas.id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.data_venda', '>=', $ini)
            ->where('vendas.data_venda', '<=', $fim . ' 23:59:59')
            ->select('tributos_venda.tipo_tributo', DB::raw('SUM(tributos_venda.valor) as total'))
            ->groupBy('tributos_venda.tipo_tributo')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->tipo_tributo] = (float) $r->total;
        }

        return $out;
    }

    /** @return array<string, float> */
    protected static function creditosPeriodo(int $empresaId, string $ini, string $fim): array
    {
        if (! Schema::hasTable('creditos_fiscais_entrada')) {
            return [];
        }
        $rows = DB::table('creditos_fiscais_entrada')
            ->join('notas_fiscais_entrada', 'creditos_fiscais_entrada.nota_fiscal_entrada_id', '=', 'notas_fiscais_entrada.id')
            ->where('creditos_fiscais_entrada.empresa_id', $empresaId)
            ->where('notas_fiscais_entrada.data_entrada', '>=', $ini)
            ->where('notas_fiscais_entrada.data_entrada', '<=', $fim . ' 23:59:59')
            ->select('creditos_fiscais_entrada.tipo_tributo', DB::raw('SUM(creditos_fiscais_entrada.valor_potencial) as total'))
            ->groupBy('creditos_fiscais_entrada.tipo_tributo')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->tipo_tributo] = (float) $r->total;
        }

        return $out;
    }
}
