<?php

namespace App\Support\Financeiro;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolida dados financeiros existentes (fechamento, boletos, proventos, estoque)
 * com as novas tabelas gerenciais para dashboard, DRE, CMV e indicadores.
 */
class FinanceiroGerencialCalculo
{
    /** Saúde >= 80% verde; 50–79% amarelo; < 50% vermelho */
    public const SAUDE_SAUDAVEL = 80;

    public const SAUDE_ATENCAO = 50;

    public static function periodoPadrao(): array
    {
        $ate = date('Y-m-d');
        $de = date('Y-m-01');

        return compact('de', 'ate');
    }

    public static function competenciaAtual(): string
    {
        return date('Y-m');
    }

    public static function statusSaude(float $pct): string
    {
        if ($pct >= self::SAUDE_SAUDAVEL) {
            return 'saudavel';
        }
        if ($pct >= self::SAUDE_ATENCAO) {
            return 'atencao';
        }

        return 'critico';
    }

    public static function labelSaude(string $status): string
    {
        return match ($status) {
            'saudavel' => 'Saudável',
            'atencao' => 'Atenção',
            default => 'Crítico',
        };
    }

    /** Soma faturamento (campo sis das linhas do fechamento de caixa). */
    public static function faturamentoPeriodo(?string $de, ?string $ate, ?int $unidadeId = null): float
    {
        if (! Schema::hasTable('fechamentos_caixa')) {
            return 0.0;
        }
        $q = DB::table('fechamentos_caixa');
        if ($de) {
            $q->whereDate('data_fechamento', '>=', $de);
        }
        if ($ate) {
            $q->whereDate('data_fechamento', '<=', $ate);
        }
        if ($unidadeId) {
            $q->where('unidade_id', $unidadeId);
        }
        $total = 0.0;
        foreach ($q->get(['linhas_json']) as $row) {
            $total += self::somaLinhasFechamento($row->linhas_json ?? null, 'sis');
        }

        return round($total, 2);
    }

    /** Faturamento agrupado por unidade. */
    public static function faturamentoPorUnidade(?string $de, ?string $ate): array
    {
        if (! Schema::hasTable('fechamentos_caixa')) {
            return [];
        }
        $q = DB::table('fechamentos_caixa as f')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->select('f.unidade_id', 'u.nome as unidade_nome', 'f.linhas_json');
        if ($de) {
            $q->whereDate('f.data_fechamento', '>=', $de);
        }
        if ($ate) {
            $q->whereDate('f.data_fechamento', '<=', $ate);
        }
        $map = [];
        foreach ($q->get() as $row) {
            $uid = (int) ($row->unidade_id ?? 0);
            if (! isset($map[$uid])) {
                $map[$uid] = [
                    'unidade_id' => $uid ?: null,
                    'unidade_nome' => $row->unidade_nome ?? 'Sem unidade',
                    'faturamento' => 0.0,
                ];
            }
            $map[$uid]['faturamento'] += self::somaLinhasFechamento($row->linhas_json ?? null, 'sis');
        }
        foreach ($map as &$m) {
            $m['faturamento'] = round($m['faturamento'], 2);
        }

        return array_values($map);
    }

    private static function somaLinhasFechamento($json, string $campo): float
    {
        if (is_string($json)) {
            try {
                $linhas = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                return 0.0;
            }
        } elseif (is_array($json)) {
            $linhas = $json;
        } else {
            return 0.0;
        }
        $s = 0.0;
        foreach ($linhas as $L) {
            if (is_array($L)) {
                $s += (float) ($L[$campo] ?? 0);
            }
        }

        return $s;
    }

    /** Saídas de estoque (consumo, produção, perda) × custo da movimentação ou média do lote. */
    private static function exprCustoSaidaMovimentacao(): string
    {
        return 'COALESCE(NULLIF(m.custo_unitario, 0), c.custo_medio, 0)';
    }

    /** Query base: SAIDA exceto transferência. */
    private static function querySaidasEstoque(?string $de, ?string $ate, ?int $unidadeId = null)
    {
        $q = DB::table('movimentacoes as m')
            ->where('m.tipo', 'SAIDA')
            ->where('m.motivo', '!=', 'TRANSFERENCIA');
        if ($de) {
            $q->whereDate('m.data_mov', '>=', $de);
        }
        if ($ate) {
            $q->whereDate('m.data_mov', '<=', $ate);
        }
        if ($unidadeId) {
            $q->where('m.de_unidade_id', $unidadeId);
        }

        return $q;
    }

    private static function subqueryCustoMedioLotes()
    {
        if (! Schema::hasTable('stock_lotes')) {
            return null;
        }

        return DB::table('stock_lotes')
            ->select('produto_id', 'unidade_id', DB::raw('AVG(custo_unitario) as custo_medio'))
            ->groupBy('produto_id', 'unidade_id');
    }

    /** Custo total das saídas de estoque no período (alias cmv para compatibilidade). */
    public static function cmvPeriodo(?string $de, ?string $ate, ?int $unidadeId = null): float
    {
        if (! Schema::hasTable('movimentacoes')) {
            return 0.0;
        }
        $custoSub = self::subqueryCustoMedioLotes();
        $q = self::querySaidasEstoque($de, $ate, $unidadeId);
        if ($custoSub) {
            $q->leftJoinSub($custoSub, 'c', function ($join) {
                $join->on('c.produto_id', '=', 'm.produto_id')
                    ->on('c.unidade_id', '=', 'm.de_unidade_id');
            });
            $expr = self::exprCustoSaidaMovimentacao();
            $val = (float) ($q->selectRaw("COALESCE(SUM(m.qtd * ({$expr})), 0) as total")->value('total') ?? 0);
        } else {
            $hasCol = Schema::hasColumn('movimentacoes', 'custo_unitario');
            if ($hasCol) {
                $val = (float) (self::querySaidasEstoque($de, $ate, $unidadeId)
                    ->selectRaw('COALESCE(SUM(m.qtd * COALESCE(NULLIF(m.custo_unitario, 0), 0)), 0) as total')
                    ->value('total') ?? 0);
            } else {
                $val = 0.0;
            }
        }

        return round($val, 2);
    }

    public static function cmvDetalhado(?string $de, ?string $ate, ?int $unidadeId = null): array
    {
        $total = self::cmvPeriodo($de, $ate, $unidadeId);
        $faturamento = self::faturamentoPeriodo($de, $ate, $unidadeId);
        $pct = $faturamento > 0 ? round(($total / $faturamento) * 100, 2) : 0.0;
        $margemEstimada = round($faturamento - $total, 2);

        $porProduto = [];
        $porUnidade = [];
        $porMotivo = [];
        $saidasSemCusto = 0;

        if (Schema::hasTable('movimentacoes')) {
            $custoSub = self::subqueryCustoMedioLotes();
            $expr = self::exprCustoSaidaMovimentacao();

            $qMotivo = self::querySaidasEstoque($de, $ate, $unidadeId);
            if ($custoSub) {
                $qMotivo->leftJoinSub($custoSub, 'c', function ($join) {
                    $join->on('c.produto_id', '=', 'm.produto_id')
                        ->on('c.unidade_id', '=', 'm.de_unidade_id');
                });
            }
            $motivoRows = $qMotivo
                ->select(
                    'm.motivo',
                    DB::raw($custoSub
                        ? "COALESCE(SUM(m.qtd * ({$expr})), 0) as custo"
                        : 'COALESCE(SUM(m.qtd * COALESCE(NULLIF(m.custo_unitario, 0), 0)), 0) as custo')
                )
                ->groupBy('m.motivo')
                ->get();
            $labels = [
                'CONSUMO' => 'Consumo',
                'PRODUCAO' => 'Produção',
                'PERDA' => 'Perda',
            ];
            foreach ($labels as $key => $label) {
                $porMotivo[] = [
                    'motivo' => $key,
                    'motivo_label' => $label,
                    'custo' => 0.0,
                ];
            }
            $motivoMap = [];
            foreach ($porMotivo as $i => $row) {
                $motivoMap[$row['motivo']] = $i;
            }
            foreach ($motivoRows as $r) {
                $key = strtoupper((string) ($r->motivo ?? ''));
                $custo = round((float) ($r->custo ?? 0), 2);
                if (isset($motivoMap[$key])) {
                    $porMotivo[$motivoMap[$key]]['custo'] = $custo;
                } else {
                    $porMotivo[] = [
                        'motivo' => $key ?: 'OUTROS',
                        'motivo_label' => $labels[$key] ?? ($key ?: 'Outros'),
                        'custo' => $custo,
                    ];
                }
            }
            usort($porMotivo, fn ($a, $b) => $b['custo'] <=> $a['custo']);

            if ($custoSub) {
                $q = self::querySaidasEstoque($de, $ate, $unidadeId)
                    ->leftJoin('produtos as p', 'm.produto_id', '=', 'p.id')
                    ->leftJoinSub($custoSub, 'c', function ($join) {
                        $join->on('c.produto_id', '=', 'm.produto_id')
                            ->on('c.unidade_id', '=', 'm.de_unidade_id');
                    })
                    ->leftJoin('unidades as u', 'm.de_unidade_id', '=', 'u.id');

                $rows = $q->select(
                    'm.produto_id',
                    'p.nome as produto_nome',
                    'm.de_unidade_id as unidade_id',
                    'u.nome as unidade_nome',
                    DB::raw("SUM(m.qtd * ({$expr})) as cmv")
                )->groupBy('m.produto_id', 'p.nome', 'm.de_unidade_id', 'u.nome')->get();

                $qSemCusto = self::querySaidasEstoque($de, $ate, $unidadeId)
                    ->leftJoinSub($custoSub, 'c', function ($join) {
                        $join->on('c.produto_id', '=', 'm.produto_id')
                            ->on('c.unidade_id', '=', 'm.de_unidade_id');
                    });
                $saidasSemCusto = (int) ($qSemCusto
                    ->whereRaw("({$expr}) <= 0")
                    ->count() ?? 0);
            } else {
                $q = self::querySaidasEstoque($de, $ate, $unidadeId)
                    ->leftJoin('produtos as p', 'm.produto_id', '=', 'p.id')
                    ->leftJoin('unidades as u', 'm.de_unidade_id', '=', 'u.id');
                $rows = $q->select(
                    'm.produto_id',
                    'p.nome as produto_nome',
                    'm.de_unidade_id as unidade_id',
                    'u.nome as unidade_nome',
                    DB::raw('SUM(m.qtd * COALESCE(NULLIF(m.custo_unitario, 0), 0)) as cmv')
                )->groupBy('m.produto_id', 'p.nome', 'm.de_unidade_id', 'u.nome')->get();
                $saidasSemCusto = (int) (self::querySaidasEstoque($de, $ate, $unidadeId)
                    ->where(function ($w) {
                        $w->whereNull('m.custo_unitario')->orWhere('m.custo_unitario', '<=', 0);
                    })
                    ->count() ?? 0);
            }

            foreach ($rows as $r) {
                $cmv = round((float) $r->cmv, 2);
                if ($cmv <= 0) {
                    continue;
                }
                $porProduto[] = [
                    'produto_id' => (int) $r->produto_id,
                    'produto_nome' => $r->produto_nome ?? '—',
                    'cmv' => $cmv,
                ];
                $uid = (int) ($r->unidade_id ?? 0);
                if (! isset($porUnidade[$uid])) {
                    $porUnidade[$uid] = [
                        'unidade_id' => $uid ?: null,
                        'unidade_nome' => $r->unidade_nome ?? '—',
                        'cmv' => 0.0,
                    ];
                }
                $porUnidade[$uid]['cmv'] += $cmv;
            }
        }

        usort($porProduto, fn ($a, $b) => $b['cmv'] <=> $a['cmv']);
        foreach ($porUnidade as &$u) {
            $u['cmv'] = round($u['cmv'], 2);
        }

        return [
            'cmv_total' => $total,
            'custo_saidas_total' => $total,
            'faturamento' => $faturamento,
            'margem_estimada' => $margemEstimada,
            'percentual_sobre_faturamento' => $pct,
            'percentual_custo_sobre_faturamento' => $pct,
            'por_motivo' => $porMotivo,
            'saidas_sem_custo' => $saidasSemCusto,
            'por_produto' => $porProduto,
            'por_unidade' => array_values($porUnidade),
            'observacao' => 'Inclui consumo, produção e perda. Transferências não entram. Custo usa o valor da saída ou média do lote.',
        ];
    }

    public static function totalBoletosPagos(?string $de, ?string $ate, ?int $unidadeId = null): float
    {
        if (! Schema::hasTable('boletos')) {
            return 0.0;
        }
        $q = DB::table('boletos')->where('status', 'PAGO');
        if ($de) {
            $q->whereDate('data_pagamento', '>=', $de);
        }
        if ($ate) {
            $q->whereDate('data_pagamento', '<=', $ate);
        }
        if ($unidadeId && Schema::hasColumn('boletos', 'unidade_id')) {
            $q->where('unidade_id', $unidadeId);
        }
        // Boletos já espelhados no fluxo de caixa (ex.: impostos) entram só pelo lançamento.
        if (Schema::hasTable('financeiro_lancamentos')) {
            $q->whereNotExists(function ($sub) {
                $sub->from('financeiro_lancamentos as fl')
                    ->whereColumn('fl.origem_id', 'boletos.id')
                    ->where('fl.origem_tipo', 'boleto')
                    ->whereNull('fl.deleted_at')
                    ->whereIn('fl.status', ['realizado', 'atrasado']);
            });
        }

        return round((float) $q->sum('valor'), 2);
    }

    public static function boletosVencidos(?int $unidadeId = null): array
    {
        if (! Schema::hasTable('boletos')) {
            return ['quantidade' => 0, 'valor' => 0.0];
        }
        $q = DB::table('boletos')->where('status', 'VENCIDO');
        if ($unidadeId && Schema::hasColumn('boletos', 'unidade_id')) {
            $q->where('unidade_id', $unidadeId);
        }

        return [
            'quantidade' => $q->count(),
            'valor' => round((float) $q->sum('valor'), 2),
        ];
    }

    public static function despesasFixasMes(?int $unidadeId = null): float
    {
        if (! Schema::hasTable('despesas_fixas')) {
            return 0.0;
        }
        $rows = DB::table('despesas_fixas')->where('status', 'ativo')->get();
        $total = 0.0;
        foreach ($rows as $r) {
            if ($unidadeId) {
                $aplicaTodas = (int) ($r->aplica_todas_unidades ?? 0) === 1;
                $uids = [];
                if (is_string($r->unidade_ids ?? null)) {
                    $uids = json_decode($r->unidade_ids, true) ?: [];
                } elseif (is_array($r->unidade_ids ?? null)) {
                    $uids = $r->unidade_ids;
                }
                if (! $aplicaTodas && ! in_array($unidadeId, array_map('intval', $uids), true)) {
                    continue;
                }
            }
            $total += (float) ($r->valor ?? 0);
        }

        return round($total, 2);
    }

    /** Proventos finalizados no período (folha / custos pessoal). */
    public static function proventosPeriodo(?string $de, ?string $ate, ?int $unidadeId = null): float
    {
        if (! Schema::hasTable('proventos')) {
            return 0.0;
        }
        $q = DB::table('proventos')->where('status', 'finalizado');
        if ($de) {
            $q->whereDate('data_provento', '>=', $de);
        }
        if ($ate) {
            $q->whereDate('data_provento', '<=', $ate);
        }
        if ($unidadeId) {
            $q->where('unidade_id', $unidadeId);
        }

        return round((float) $q->sum('valor'), 2);
    }

    public static function valeConsumoPeriodo(?string $de, ?string $ate, ?int $unidadeId = null): float
    {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return 0.0;
        }
        $q = DB::table('financeiro_vale_consumo');
        $dataCol = Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')
            ? 'data_lancamento'
            : (Schema::hasColumn('financeiro_vale_consumo', 'data_referencia') ? 'data_referencia' : null);
        if ($dataCol && $de) {
            $q->whereDate($dataCol, '>=', $de);
        }
        if ($dataCol && $ate) {
            $q->whereDate($dataCol, '<=', $ate);
        }
        if ($unidadeId && Schema::hasColumn('financeiro_vale_consumo', 'unidade_id')) {
            $q->where('unidade_id', $unidadeId);
        }
        $col = Schema::hasColumn('financeiro_vale_consumo', 'valor_total') ? 'valor_total' : 'valor';

        return round((float) $q->sum($col), 2);
    }

    public static function investimentosCarteira(): float
    {
        if (! Schema::hasTable('investimento_carteira')) {
            return 0.0;
        }
        $q = DB::table('investimento_carteira');
        if (Schema::hasColumn('investimento_carteira', 'status')) {
            $q->whereIn('status', ['ativo', 'ATIVO', 'aplicado']);
        }

        return round((float) $q->sum('valor_aplicado'), 2);
    }

    public static function lancamentosFluxo(?string $de, ?string $ate, ?int $unidadeId = null, ?string $tipo = null): array
    {
        if (! Schema::hasTable('financeiro_lancamentos')) {
            return ['entradas' => 0.0, 'saidas' => 0.0];
        }
        $base = DB::table('financeiro_lancamentos')
            ->whereNull('deleted_at')
            ->whereIn('status', ['realizado', 'atrasado']);
        if ($de) {
            $base->where(function ($q) use ($de) {
                $q->whereDate('data_pagamento', '>=', $de)
                    ->orWhere(function ($q2) use ($de) {
                        $q2->whereNull('data_pagamento')->whereDate('data_competencia', '>=', $de);
                    });
            });
        }
        if ($ate) {
            $base->where(function ($q) use ($ate) {
                $q->whereDate('data_pagamento', '<=', $ate)
                    ->orWhere(function ($q2) use ($ate) {
                        $q2->whereNull('data_pagamento')->whereDate('data_competencia', '<=', $ate);
                    });
            });
        }
        if ($unidadeId) {
            $base->where('unidade_id', $unidadeId);
        }
        $entradas = (clone $base)->where('tipo', 'entrada');
        $saidas = (clone $base)->where('tipo', 'saida');
        if ($tipo === 'entrada') {
            return ['entradas' => round((float) $entradas->sum('valor'), 2), 'saidas' => 0.0];
        }
        if ($tipo === 'saida') {
            return ['entradas' => 0.0, 'saidas' => round((float) $saidas->sum('valor'), 2)];
        }

        return [
            'entradas' => round((float) $entradas->sum('valor'), 2),
            'saidas' => round((float) $saidas->sum('valor'), 2),
        ];
    }

    public static function contasReceberVencidas(?int $unidadeId = null): array
    {
        if (! Schema::hasTable('financeiro_contas_receber')) {
            return ['quantidade' => 0, 'valor' => 0.0];
        }
        $hoje = date('Y-m-d');
        $q = DB::table('financeiro_contas_receber')
            ->whereNull('deleted_at')
            ->where(function ($sub) use ($hoje) {
                $sub->where('status', 'vencido')
                    ->orWhere(function ($s) use ($hoje) {
                        $s->where('status', 'aberto')->whereDate('data_vencimento', '<', $hoje);
                    });
            });
        if ($unidadeId) {
            $q->where('unidade_id', $unidadeId);
        }

        return [
            'quantidade' => $q->count(),
            'valor' => round((float) $q->sum('valor'), 2),
        ];
    }

    public static function contasReceberRecebidas(?string $de, ?string $ate, ?int $unidadeId = null): float
    {
        if (! Schema::hasTable('financeiro_contas_receber')) {
            return 0.0;
        }
        $q = DB::table('financeiro_contas_receber')
            ->whereNull('deleted_at')
            ->where('status', 'recebido');
        if ($de) {
            $q->whereDate('data_recebimento', '>=', $de);
        }
        if ($ate) {
            $q->whereDate('data_recebimento', '<=', $ate);
        }
        if ($unidadeId) {
            $q->where('unidade_id', $unidadeId);
        }

        return round((float) $q->sum('valor'), 2);
    }

    /** Consolida entradas e saídas do período para dashboard e DRE. */
    public static function consolidarPeriodo(?string $de, ?string $ate, ?int $unidadeId = null): array
    {
        $faturamento = self::faturamentoPeriodo($de, $ate, $unidadeId);
        $fluxo = self::lancamentosFluxo($de, $ate, $unidadeId);
        $recebimentos = self::contasReceberRecebidas($de, $ate, $unidadeId);
        $boletosPagos = self::totalBoletosPagos($de, $ate, $unidadeId);
        $proventos = self::proventosPeriodo($de, $ate, $unidadeId);
        $vale = self::valeConsumoPeriodo($de, $ate, $unidadeId);
        $despFixas = self::despesasFixasMes($unidadeId);
        $cmv = self::cmvPeriodo($de, $ate, $unidadeId);
        $investimentos = self::investimentosCarteira();

        // Entradas: faturamento + fluxo entrada + recebimentos
        $totalEntradas = round($faturamento + $fluxo['entradas'] + $recebimentos, 2);
        // Saídas: boletos pagos + proventos + vale + fluxo saída + despesas fixas proporcionais ao período
        $totalSaidas = round($boletosPagos + $proventos + $vale + $fluxo['saidas'] + $despFixas, 2);
        $lucroPrejuizo = round($totalEntradas - $totalSaidas - $cmv, 2);
        $margemLiquida = $faturamento > 0 ? round(($lucroPrejuizo / $faturamento) * 100, 2) : 0.0;
        $margemBruta = $faturamento > 0 ? round((($faturamento - $cmv) / $faturamento) * 100, 2) : 0.0;

        $custosFixos = round($despFixas + $proventos, 2);
        $margemContrib = $faturamento > 0 ? ($faturamento - $cmv) / $faturamento : 0;
        $pontoEquilibrio = $margemContrib > 0.01 ? round($custosFixos / $margemContrib, 2) : null;

        $caixaDisponivel = round($investimentos + $fluxo['entradas'] - $fluxo['saidas'], 2);
        $pagarVenc = self::boletosVencidos($unidadeId);
        $receberVenc = self::contasReceberVencidas($unidadeId);

        $saude = self::calcularSaudeFinanceira([
            'faturamento' => $faturamento,
            'lucro' => $lucroPrejuizo,
            'caixa' => $caixaDisponivel,
            'despesas_mes' => $despFixas + $proventos,
            'pagar_vencido' => $pagarVenc['valor'],
            'margem_liquida' => $margemLiquida,
        ]);

        return [
            'faturamento_total' => $faturamento,
            'faturamento_por_unidade' => self::faturamentoPorUnidade($de, $ate),
            'total_entradas' => $totalEntradas,
            'total_saidas' => $totalSaidas,
            'lucro_prejuizo' => $lucroPrejuizo,
            'caixa_disponivel' => $caixaDisponivel,
            'contas_pagar_vencidas' => $pagarVenc,
            'contas_receber_vencidas' => $receberVenc,
            'despesas_fixas_mes' => $despFixas,
            'folha_proventos_mes' => $proventos,
            'cmv_estimado' => $cmv,
            'margem_liquida' => $margemLiquida,
            'margem_bruta' => $margemBruta,
            'ponto_equilibrio' => $pontoEquilibrio,
            'investimentos_reservas' => $investimentos,
            'saude_financeira' => $saude,
        ];
    }

    /**
     * Score composto 0–100: liquidez, margem, inadimplência a pagar.
     * Regra: >= 80 saudável; 50–79 atenção; < 50 crítico.
     */
    public static function calcularSaudeFinanceira(array $d): array
    {
        $faturamento = max(0.01, (float) ($d['faturamento'] ?? 0));
        $despesasMes = max(0.01, (float) ($d['despesas_mes'] ?? 0));
        $caixa = (float) ($d['caixa'] ?? 0);
        $margem = (float) ($d['margem_liquida'] ?? 0);
        $pagarVenc = (float) ($d['pagar_vencido'] ?? 0);

        $liquidez = min(100, max(0, ($caixa / $despesasMes) * 50));
        $margemScore = min(100, max(0, $margem + 50));
        $inadimplencia = $pagarVenc > 0 ? min(100, ($pagarVenc / $faturamento) * 100) : 0;
        $inadimplenciaScore = max(0, 100 - $inadimplencia * 2);

        $pct = round(($liquidez * 0.35) + ($margemScore * 0.40) + ($inadimplenciaScore * 0.25), 1);
        $pct = min(100, max(0, $pct));
        $status = self::statusSaude($pct);

        return [
            'percentual' => $pct,
            'status' => $status,
            'label' => self::labelSaude($status),
            'componentes' => [
                'liquidez' => round($liquidez, 1),
                'margem' => round($margemScore, 1),
                'inadimplencia_pagar' => round($inadimplenciaScore, 1),
            ],
        ];
    }

    public static function dre(?string $de, ?string $ate, ?int $unidadeId = null, ?int $categoriaId = null): array
    {
        $c = self::consolidarPeriodo($de, $ate, $unidadeId);
        $faturamento = $c['faturamento_total'];
        $deducoes = round($faturamento * 0.06, 2); // estimativa impostos 6% — ajustável
        $receitaLiquida = round($faturamento - $deducoes, 2);
        $cmv = $c['cmv_estimado'];
        $lucroBruto = round($receitaLiquida - $cmv, 2);
        $despOper = round($c['despesas_fixas_mes'] + $c['folha_proventos_mes'], 2);
        $outrasDesp = round($c['total_saidas'] - $c['folha_proventos_mes'] - $c['despesas_fixas_mes'], 2);
        if ($outrasDesp < 0) {
            $outrasDesp = 0.0;
        }
        $resultadoOp = round($lucroBruto - $despOper - $outrasDesp, 2);
        $invest = $c['investimentos_reservas'];
        $lucroLiquido = round($resultadoOp, 2);

        return [
            'receita_bruta' => $faturamento,
            'deducoes_impostos' => $deducoes,
            'receita_liquida' => $receitaLiquida,
            'cmv' => $cmv,
            'lucro_bruto' => $lucroBruto,
            'despesas_operacionais' => $despOper,
            'folha_proventos' => $c['folha_proventos_mes'],
            'despesas_fixas' => $c['despesas_fixas_mes'],
            'outras_despesas' => $outrasDesp,
            'resultado_operacional' => $resultadoOp,
            'investimentos_reservas' => $invest,
            'lucro_liquido' => $lucroLiquido,
            'margem_bruta_pct' => $faturamento > 0 ? round(($lucroBruto / $faturamento) * 100, 2) : 0,
            'margem_liquida_pct' => $faturamento > 0 ? round(($lucroLiquido / $faturamento) * 100, 2) : 0,
        ];
    }

    public static function indicadores(?string $de, ?string $ate, ?int $unidadeId = null): array
    {
        $c = self::consolidarPeriodo($de, $ate, $unidadeId);
        $faturamento = max(0.01, $c['faturamento_total']);
        $despesas = max(0.01, $c['despesas_fixas_mes'] + $c['folha_proventos_mes']);
        $caixa = $c['caixa_disponivel'];
        $pagarVenc = $c['contas_pagar_vencidas']['valor'];
        $receberVenc = $c['contas_receber_vencidas']['valor'];
        $cmv = $c['cmv_estimado'];

        $liquidez = round($caixa / $despesas, 2);
        $endividamento = $faturamento > 0 ? round(($pagarVenc / $faturamento) * 100, 2) : 0;
        $capitalGiro = round($caixa + $receberVenc - $pagarVenc, 2);
        $margemBruta = $faturamento > 0 ? round((($faturamento - $cmv) / $faturamento) * 100, 2) : 0;

        return [
            'liquidez' => $liquidez,
            'margem_liquida' => $c['margem_liquida'],
            'margem_bruta' => $margemBruta,
            'endividamento' => $endividamento,
            'capital_giro' => $capitalGiro,
            'ponto_equilibrio' => $c['ponto_equilibrio'],
            'saude_financeira' => $c['saude_financeira'],
        ];
    }

    /** Saldo e projeção de fluxo de caixa (30/60/90 dias). */
    public static function fluxoCaixaRelatorio(?string $de, ?string $ate, ?int $unidadeId = null): array
    {
        $saldoInicial = 0.0;
        if (Schema::hasTable('financeiro_lancamentos') && $de) {
            $antes = DB::table('financeiro_lancamentos')
                ->whereNull('deleted_at')
                ->whereIn('status', ['realizado', 'atrasado'])
                ->where(function ($q) use ($de) {
                    $q->whereDate('data_pagamento', '<', $de)
                        ->orWhere(function ($q2) use ($de) {
                            $q2->whereNull('data_pagamento')->whereDate('data_competencia', '<', $de);
                        });
                });
            if ($unidadeId) {
                $antes->where('unidade_id', $unidadeId);
            }
            $ent = (float) (clone $antes)->where('tipo', 'entrada')->sum('valor');
            $sai = (float) (clone $antes)->where('tipo', 'saida')->sum('valor');
            $saldoInicial = round($ent - $sai, 2);
        }

        $fluxo = self::lancamentosFluxo($de, $ate, $unidadeId);
        $saldoFinal = round($saldoInicial + $fluxo['entradas'] - $fluxo['saidas'], 2);

        $projecoes = [];
        foreach ([30, 60, 90] as $dias) {
            $fim = date('Y-m-d', strtotime("+{$dias} days"));
            $hoje = date('Y-m-d');
            $prevEnt = 0.0;
            $prevSai = 0.0;
            if (Schema::hasTable('financeiro_lancamentos')) {
                $q = DB::table('financeiro_lancamentos')
                    ->whereNull('deleted_at')
                    ->where('status', 'previsto')
                    ->where(function ($sub) use ($hoje, $fim) {
                        $sub->whereBetween(DB::raw('COALESCE(data_pagamento, data_competencia)'), [$hoje, $fim]);
                    });
                if ($unidadeId) {
                    $q->where('unidade_id', $unidadeId);
                }
                $prevEnt = (float) (clone $q)->where('tipo', 'entrada')->sum('valor');
                $prevSai = (float) (clone $q)->where('tipo', 'saida')->sum('valor');
            }
            $projecoes["dias_{$dias}"] = [
                'dias' => $dias,
                'saldo_projetado' => round($saldoFinal + $prevEnt - $prevSai, 2),
                'entradas_previstas' => round($prevEnt, 2),
                'saidas_previstas' => round($prevSai, 2),
            ];
        }

        return [
            'saldo_inicial' => $saldoInicial,
            'entradas' => $fluxo['entradas'],
            'saidas' => $fluxo['saidas'],
            'saldo_final' => $saldoFinal,
            'projecoes' => $projecoes,
        ];
    }

    public static function orcamentoComparativo(string $competencia, ?int $unidadeId = null): array
    {
        $de = $competencia.'-01';
        $ate = date('Y-m-t', strtotime($de));
        $realizado = self::consolidarPeriodo($de, $ate, $unidadeId);

        $meta = ['meta_faturamento' => 0, 'meta_despesa' => 0, 'meta_lucro' => 0];
        if (Schema::hasTable('financeiro_orcamentos')) {
            $q = DB::table('financeiro_orcamentos')->where('competencia', $competencia);
            if ($unidadeId) {
                $q->where('unidade_id', $unidadeId);
            } else {
                $q->whereNull('unidade_id');
            }
            $row = $q->first();
            if ($row) {
                $meta = [
                    'meta_faturamento' => (float) $row->meta_faturamento,
                    'meta_despesa' => (float) $row->meta_despesa,
                    'meta_lucro' => (float) $row->meta_lucro,
                ];
            }
        }

        return [
            'competencia' => $competencia,
            'meta' => $meta,
            'realizado' => [
                'faturamento' => $realizado['faturamento_total'],
                'despesa' => $realizado['total_saidas'],
                'lucro' => $realizado['lucro_prejuizo'],
            ],
            'variacao' => [
                'faturamento' => round($realizado['faturamento_total'] - $meta['meta_faturamento'], 2),
                'despesa' => round($realizado['total_saidas'] - $meta['meta_despesa'], 2),
                'lucro' => round($realizado['lucro_prejuizo'] - $meta['meta_lucro'], 2),
            ],
        ];
    }
}
