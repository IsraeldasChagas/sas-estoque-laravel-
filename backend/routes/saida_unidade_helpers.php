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
}
