<?php

namespace App\Support\Investimento;

/**
 * Cálculos de rendimento bruto/líquido e simulação de investimentos.
 */
class InvestimentoCalculo
{
    /** Objetivos de reserva disponíveis no cadastro. */
    public const OBJETIVOS = [
        'rescisoes' => 'Rescisões',
        'ferias' => 'Férias',
        'decimo_terceiro' => '13º salário',
        'impostos' => 'Impostos',
        'emergencia' => 'Emergência',
        'expansao' => 'Expansão',
        'outros' => 'Outros',
    ];

    /** Tipos de investimento com liquidez associada. */
    public const TIPOS = [
        'tesouro_selic' => ['label' => 'Tesouro Selic', 'liquidez' => 'alta'],
        'tesouro_ipca' => ['label' => 'Tesouro IPCA+', 'liquidez' => 'media'],
        'tesouro_prefixado' => ['label' => 'Tesouro Prefixado', 'liquidez' => 'baixa'],
        'cdb_liquidez' => ['label' => 'CDB liquidez diária', 'liquidez' => 'alta'],
        'fundo_di' => ['label' => 'Fundo DI', 'liquidez' => 'alta'],
        'outros' => ['label' => 'Outros', 'liquidez' => 'media'],
    ];

    /** Objetivos que exigem alerta de data alvo. */
    public const OBJETIVOS_ALERTA_DATA = ['rescisoes', 'ferias', 'decimo_terceiro'];

    /** Tipos recomendados para reserva de emergência (alta liquidez). */
    public const TIPOS_ALTA_LIQUIDEZ = ['tesouro_selic', 'cdb_liquidez', 'fundo_di'];

    /**
     * Converte taxa anual (%) em taxa mensal decimal.
     */
    public static function taxaAnualParaMensal(float $taxaAnualPercent): float
    {
        if ($taxaAnualPercent <= 0) {
            return 0.0;
        }

        return pow(1 + ($taxaAnualPercent / 100), 1 / 12) - 1;
    }

    /**
     * Converte taxa mensal (%) em taxa anual (%).
     */
    public static function taxaMensalParaAnual(float $taxaMensalPercent): float
    {
        if ($taxaMensalPercent <= 0) {
            return 0.0;
        }

        return (pow(1 + ($taxaMensalPercent / 100), 12) - 1) * 100;
    }

    /**
     * Simula valor futuro com aporte mensal (juros compostos).
     *
     * @return array{valor_bruto: float, total_aportado: float, rendimento_bruto: float, imposto: float, rendimento_liquido: float, valor_liquido: float, taxa_mensal_percent: float, taxa_anual_percent: float}
     */
    public static function simular(
        float $valorInicial,
        float $aporteMensal,
        int $prazoMeses,
        ?float $taxaAnualPercent = null,
        ?float $taxaMensalPercent = null
    ): array {
        $prazoMeses = max(0, $prazoMeses);
        $totalAportado = $valorInicial + ($aporteMensal * $prazoMeses);

        if ($taxaMensalPercent !== null && $taxaMensalPercent > 0) {
            $r = $taxaMensalPercent / 100;
            $taxaAnualCalc = self::taxaMensalParaAnual($taxaMensalPercent);
        } elseif ($taxaAnualPercent !== null && $taxaAnualPercent > 0) {
            $r = self::taxaAnualParaMensal($taxaAnualPercent);
            $taxaAnualCalc = $taxaAnualPercent;
            $taxaMensalPercent = $r * 100;
        } else {
            return [
                'valor_bruto' => round($totalAportado, 2),
                'total_aportado' => round($totalAportado, 2),
                'rendimento_bruto' => 0.0,
                'imposto' => 0.0,
                'rendimento_liquido' => 0.0,
                'valor_liquido' => round($totalAportado, 2),
                'taxa_mensal_percent' => 0.0,
                'taxa_anual_percent' => 0.0,
            ];
        }

        $n = $prazoMeses;
        if ($n <= 0) {
            $valorBruto = $valorInicial;
        } elseif ($r == 0.0) {
            $valorBruto = $totalAportado;
        } else {
            $fvInicial = $valorInicial * pow(1 + $r, $n);
            $fvAportes = $aporteMensal * ((pow(1 + $r, $n) - 1) / $r);
            $valorBruto = $fvInicial + $fvAportes;
        }

        $rendimentoBruto = max(0, $valorBruto - $totalAportado);
        $dias = $prazoMeses * 30;
        $imposto = self::calcularImpostoRenda($dias, $rendimentoBruto);
        $rendimentoLiquido = max(0, $rendimentoBruto - $imposto);
        $valorLiquido = $totalAportado + $rendimentoLiquido;

        return [
            'valor_bruto' => round($valorBruto, 2),
            'total_aportado' => round($totalAportado, 2),
            'rendimento_bruto' => round($rendimentoBruto, 2),
            'imposto' => round($imposto, 2),
            'rendimento_liquido' => round($rendimentoLiquido, 2),
            'valor_liquido' => round($valorLiquido, 2),
            'taxa_mensal_percent' => round($taxaMensalPercent, 4),
            'taxa_anual_percent' => round($taxaAnualCalc, 4),
        ];
    }

    /**
     * IR regressivo sobre rendimentos de renda fixa (tabela simplificada).
     */
    public static function calcularImpostoRenda(int $dias, float $rendimento): float
    {
        if ($rendimento <= 0) {
            return 0.0;
        }
        $aliquota = match (true) {
            $dias <= 180 => 0.225,
            $dias <= 360 => 0.20,
            $dias <= 720 => 0.175,
            default => 0.15,
        };

        return round($rendimento * $aliquota, 2);
    }

    /**
     * Estima rendimento de um título já aplicado até hoje ou vencimento.
     */
    public static function estimarRendimentoCarteira(
        float $valorAplicado,
        ?float $taxaAnualPercent,
        string $dataCompra,
        ?string $dataReferencia = null
    ): array {
        $ref = $dataReferencia ?: date('Y-m-d');
        $dias = 0;
        try {
            $inicio = new \DateTime(substr($dataCompra, 0, 10));
            $fim = new \DateTime(substr($ref, 0, 10));
            $dias = max(0, (int) $inicio->diff($fim)->days);
        } catch (\Throwable $e) {
            $dias = 0;
        }

        $taxaAnual = $taxaAnualPercent ?? 0.0;
        $rendimentoBruto = 0.0;
        if ($valorAplicado > 0 && $taxaAnual > 0 && $dias > 0) {
            $rendimentoBruto = $valorAplicado * (pow(1 + ($taxaAnual / 100), $dias / 365) - 1);
        }
        $imposto = self::calcularImpostoRenda($dias, $rendimentoBruto);
        $rendimentoLiquido = max(0, $rendimentoBruto - $imposto);

        return [
            'dias' => $dias,
            'rendimento_bruto' => round($rendimentoBruto, 2),
            'imposto' => round($imposto, 2),
            'rendimento_liquido' => round($rendimentoLiquido, 2),
            'valor_estimado' => round($valorAplicado + $rendimentoLiquido, 2),
        ];
    }

    /** Verifica se a data alvo está próxima (90 dias). */
    public static function alertaDataAlvo(?string $dataAlvo, string $objetivo): ?array
    {
        if (! $dataAlvo || ! in_array($objetivo, self::OBJETIVOS_ALERTA_DATA, true)) {
            return null;
        }
        try {
            $alvo = new \DateTime(substr($dataAlvo, 0, 10));
            $hoje = new \DateTime('today');
            $dias = (int) $hoje->diff($alvo)->format('%r%a');
            if ($dias < 0) {
                return ['tipo' => 'vencido', 'dias' => abs($dias), 'mensagem' => 'Data alvo já passou.'];
            }
            if ($dias <= 90) {
                return ['tipo' => 'proximo', 'dias' => $dias, 'mensagem' => "Data alvo em {$dias} dia(s)."];
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /** Filtra tipos de investimento conforme objetivo da reserva. */
    public static function tiposPermitidosParaObjetivo(string $objetivo): array
    {
        if ($objetivo === 'emergencia') {
            return array_values(array_intersect_key(
                self::TIPOS,
                array_flip(self::TIPOS_ALTA_LIQUIDEZ)
            ));
        }

        return self::TIPOS;
    }
}
