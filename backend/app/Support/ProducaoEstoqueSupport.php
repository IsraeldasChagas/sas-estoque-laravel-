<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProducaoEstoqueSupport
{
    /**
     * Baixa estoque FIFO (mesma política de POST /saida).
     *
     * @return array{lotes_usados: list<array<string,mixed>>, custo_medio: float, lote_id: ?int, custo_total: float}
     */
    public static function baixarFifo(
        int $produtoId,
        int $unidadeId,
        float $quantidadeSolicitada,
        ?string $codigoLoteFiltro = null,
        bool $forcar = false
    ): array {
        require_once dirname(__DIR__, 2) . '/routes/saida_unidade_helpers.php';

        if ($quantidadeSolicitada <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $queryEstoque = DB::table('stock_lotes')
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0);
        if ($codigoLoteFiltro) {
            $queryEstoque->where('codigo_lote', $codigoLoteFiltro);
        }
        $estoqueDisponivel = (float) $queryEstoque->sum('quantidade');
        if ($estoqueDisponivel < $quantidadeSolicitada) {
            throw new \RuntimeException("Estoque insuficiente. Disponível: {$estoqueDisponivel}, solicitado: {$quantidadeSolicitada}.");
        }

        $queryLotes = DB::table('stock_lotes')
            ->leftJoin('lotes', function ($join) use ($produtoId, $unidadeId) {
                $join->on('lotes.numero_lote', '=', 'stock_lotes.codigo_lote')
                    ->where('lotes.produto_id', '=', $produtoId)
                    ->where('lotes.unidade_id', '=', $unidadeId);
            })
            ->where('stock_lotes.produto_id', $produtoId)
            ->where('stock_lotes.unidade_id', $unidadeId)
            ->where('stock_lotes.quantidade', '>', 0)
            ->select(
                'stock_lotes.id as stock_id',
                'lotes.id as lote_id',
                'stock_lotes.quantidade as quantidade_disponivel',
                'stock_lotes.custo_unitario',
                'lotes.data_validade',
                'stock_lotes.codigo_lote'
            )
            ->orderBy('lotes.data_validade', 'asc')
            ->orderBy('stock_lotes.id', 'asc');

        if ($codigoLoteFiltro) {
            $queryLotes->where('stock_lotes.codigo_lote', $codigoLoteFiltro);
        }

        $lotesDisponiveis = $queryLotes->get();
        if ($lotesDisponiveis->isEmpty()) {
            throw new \RuntimeException('Nenhum lote disponível para baixa.');
        }

        if (! $forcar) {
            $lotesDisponiveis = $lotesDisponiveis->filter(function ($lote) {
                return ! $lote->data_validade || $lote->data_validade >= now()->format('Y-m-d');
            });
            $disp = (float) $lotesDisponiveis->sum('quantidade_disponivel');
            if ($disp < $quantidadeSolicitada) {
                throw new \RuntimeException("Saldo insuficiente sem lotes vencidos. Disponível: {$disp}.");
            }
        }

        $quantidadeRestante = $quantidadeSolicitada;
        $lotesUsados = [];
        $totalCusto = 0.0;

        foreach ($lotesDisponiveis as $lote) {
            if ($quantidadeRestante <= 0) {
                break;
            }
            $quantidadeUsar = min($quantidadeRestante, (float) $lote->quantidade_disponivel);
            $quantidadeRestante -= $quantidadeUsar;
            $novaQuantidade = (float) $lote->quantidade_disponivel - $quantidadeUsar;

            if ($novaQuantidade <= 0) {
                DB::table('stock_lotes')->where('id', $lote->stock_id)->delete();
            } else {
                DB::table('stock_lotes')->where('id', $lote->stock_id)->update(['quantidade' => $novaQuantidade]);
            }

            if ($lote->lote_id) {
                $quantidadeTotalLote = DB::table('stock_lotes')
                    ->where('codigo_lote', $lote->codigo_lote)
                    ->where('produto_id', $produtoId)
                    ->where('unidade_id', $unidadeId)
                    ->sum('quantidade');
                DB::table('lotes')->where('id', $lote->lote_id)->update(['qtd_atual' => $quantidadeTotalLote]);
            }

            $cu = (float) ($lote->custo_unitario ?? 0);
            $lotesUsados[] = [
                'lote_id' => $lote->lote_id,
                'codigo_lote' => $lote->codigo_lote,
                'quantidade' => $quantidadeUsar,
                'custo_unitario' => $cu,
            ];
            $totalCusto += $quantidadeUsar * $cu;
        }

        if ($quantidadeRestante > 0.0001) {
            throw new \RuntimeException('Falha ao completar baixa FIFO.');
        }

        $custoMedio = $quantidadeSolicitada > 0 ? $totalCusto / $quantidadeSolicitada : 0;
        $loteIdUsado = ! empty($lotesUsados) && isset($lotesUsados[0]['lote_id']) ? (int) $lotesUsados[0]['lote_id'] : null;

        return [
            'lotes_usados' => $lotesUsados,
            'custo_medio' => $custoMedio,
            'lote_id' => $loteIdUsado,
            'custo_total' => $totalCusto,
        ];
    }

    public static function saldoDisponivel(int $produtoId, int $unidadeId): float
    {
        return (float) DB::table('stock_lotes')
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0)
            ->sum('quantidade');
    }

    /** Saldo considerando apenas lotes não vencidos (mesma regra da baixa FIFO). */
    public static function saldoDisponivelValido(int $produtoId, int $unidadeId): float
    {
        $hoje = now()->format('Y-m-d');
        $rows = DB::table('stock_lotes')
            ->leftJoin('lotes', function ($join) use ($produtoId, $unidadeId) {
                $join->on('lotes.numero_lote', '=', 'stock_lotes.codigo_lote')
                    ->where('lotes.produto_id', '=', $produtoId)
                    ->where('lotes.unidade_id', '=', $unidadeId);
            })
            ->where('stock_lotes.produto_id', $produtoId)
            ->where('stock_lotes.unidade_id', $unidadeId)
            ->where('stock_lotes.quantidade', '>', 0)
            ->select('stock_lotes.quantidade', 'lotes.data_validade')
            ->get();

        $total = 0.0;
        foreach ($rows as $r) {
            if (! $r->data_validade || $r->data_validade >= $hoje) {
                $total += (float) $r->quantidade;
            }
        }

        return $total;
    }
}
