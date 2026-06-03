<?php

/**
 * Helpers de unidade para POST /saida.
 * Arquivo separado para funcionar com `php artisan route:cache` em produção.
 */
if (!function_exists('normalizarUnidadeMedidaSaida')) {
    function normalizarUnidadeMedidaSaida($value): string
    {
        $u = strtoupper(trim((string) ($value ?? '')));
        if ($u === '' || in_array($u, ['UN', 'UNID', 'UNIDADE', 'UNIDADES'], true)) {
            return 'UND';
        }
        if ($u === 'K') {
            return 'KG';
        }
        return $u;
    }

    function grupoUnidadeMedidaSaida(string $unidade): string
    {
        $u = normalizarUnidadeMedidaSaida($unidade);
        if (in_array($u, ['G', 'KG'], true)) {
            return 'massa';
        }
        if (in_array($u, ['ML', 'L', 'KL'], true)) {
            return 'volume';
        }
        if ($u === 'UND') {
            return 'unidade';
        }
        return 'outro';
    }

    function converterQuantidadeParaUnidadeBaseSaida(float $qtd, string $deUnidade, string $paraUnidade): float
    {
        $de = normalizarUnidadeMedidaSaida($deUnidade);
        $para = normalizarUnidadeMedidaSaida($paraUnidade);
        if ($de === $para) {
            return $qtd;
        }
        $gd = grupoUnidadeMedidaSaida($de);
        $gp = grupoUnidadeMedidaSaida($para);
        if ($gd !== $gp) {
            throw new \InvalidArgumentException(
                "Unidade \"{$de}\" não é compatível com a unidade base do produto ({$para})."
            );
        }
        if ($gd === 'massa') {
            $emMenor = $de === 'KG' ? $qtd * 1000 : $qtd;
            if ($para === 'KG') {
                return $emMenor / 1000;
            }
            return $emMenor;
        }
        if ($gd === 'volume') {
            $emMenor = $qtd;
            if ($de === 'L') {
                $emMenor = $qtd * 1000;
            } elseif ($de === 'KL') {
                $emMenor = $qtd * 1000000;
            }
            if ($para === 'L') {
                return $emMenor / 1000;
            }
            if ($para === 'KL') {
                return $emMenor / 1000000;
            }
            return $emMenor;
        }
        throw new \InvalidArgumentException("Não é possível converter {$de} para {$para}.");
    }

    /** Compatível com ENUM legado (UN) e VARCHAR (UND). */
    function unidadeGravacaoMovimentacao(string $unidade): string
    {
        $u = normalizarUnidadeMedidaSaida($unidade);
        if ($u === 'UND') {
            return 'UN';
        }
        if ($u === 'KL') {
            return 'L';
        }
        return $u;
    }

    /** Extrai lotes e quantidades da observação gerada em POST /saida. */
    function parseLotesObservacaoSaida(?string $observacao): array
    {
        if (!$observacao || !preg_match('/Lotes:\s*(.+)$/u', $observacao, $match)) {
            return [];
        }
        $lotes = [];
        foreach (preg_split('/,\s*/', $match[1]) as $parte) {
            $parte = trim($parte);
            if ($parte === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s*\(([\d.]+)\)$/', $parte, $item)) {
                $lotes[] = [
                    'codigo_lote' => trim($item[1]),
                    'quantidade' => (float) $item[2],
                ];
            }
        }
        return $lotes;
    }

    /**
     * Devolve ao estoque as quantidades de uma SAÍDA excluída (mesmos lotes da movimentação).
     */
    function restaurarEstoqueAposExcluirSaida(
        int $produtoId,
        int $unidadeId,
        float $qtdTotal,
        float $custoUnitario,
        ?string $observacao,
        ?int $loteIdFallback = null
    ): void {
        $lotesObs = parseLotesObservacaoSaida($observacao);
        if (empty($lotesObs)) {
            $codigoLote = null;
            if ($loteIdFallback) {
                $lote = DB::table('lotes')->where('id', $loteIdFallback)->first();
                if ($lote && (int) $lote->produto_id === $produtoId) {
                    $codigoLote = $lote->numero_lote ?? null;
                }
            }
            if (!$codigoLote) {
                throw new \RuntimeException('Não foi possível identificar o lote desta saída para restaurar o estoque.');
            }
            $lotesObs = [['codigo_lote' => $codigoLote, 'quantidade' => $qtdTotal]];
        }

        foreach ($lotesObs as $info) {
            $codigoLote = $info['codigo_lote'];
            $qtd = (float) $info['quantidade'];
            if ($qtd <= 0 || $codigoLote === '') {
                continue;
            }

            $stock = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('codigo_lote', $codigoLote)
                ->first();

            if ($stock) {
                DB::table('stock_lotes')
                    ->where('id', $stock->id)
                    ->update(['quantidade' => (float) $stock->quantidade + $qtd]);
            } else {
                DB::table('stock_lotes')->insert([
                    'produto_id' => $produtoId,
                    'unidade_id' => $unidadeId,
                    'codigo_lote' => $codigoLote,
                    'quantidade' => $qtd,
                    'custo_unitario' => $custoUnitario,
                    'data_fabricacao' => null,
                    'data_validade' => null,
                ]);
            }

            $totalLote = DB::table('stock_lotes')
                ->where('codigo_lote', $codigoLote)
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->sum('quantidade');

            $loteRow = DB::table('lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('numero_lote', $codigoLote)
                ->first();

            if ($loteRow) {
                DB::table('lotes')->where('id', $loteRow->id)->update(['qtd_atual' => $totalLote]);
            }
        }
    }
}
