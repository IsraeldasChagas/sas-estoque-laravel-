<?php

namespace App\Support\Energia;

/**
 * Cálculos de consumo e custo de energia (equipamentos).
 * consumo_kwh = (potencia_watts * quantidade * horas_por_dia * dias_uso_mes) / 1000
 * custo_estimado = consumo_kwh * valor_kwh
 */
class EnergiaCalculo
{
    public const TENSAO_PERMITIDAS = [110, 220, 380];

    public static function calcularConsumoKwh(
        float $potenciaWatts,
        int $quantidade,
        float $horasPorDia,
        int $diasUsoMes
    ): float {
        $w = max(0, $potenciaWatts);
        $q = max(0, $quantidade);
        $h = max(0, $horasPorDia);
        $d = max(0, $diasUsoMes);

        return round(($w * $q * $h * $d) / 1000, 4);
    }

    public static function calcularCustoEstimado(float $consumoKwh, float $valorKwh): float
    {
        return round(max(0, $consumoKwh) * max(0, $valorKwh), 2);
    }

    /** @return array{consumo_kwh: float, custo_estimado: float} */
    public static function calcularTotais(array $params): array
    {
        $consumo = self::calcularConsumoKwh(
            (float) ($params['potencia_watts'] ?? 0),
            (int) ($params['quantidade'] ?? 1),
            (float) ($params['horas_por_dia'] ?? 0),
            (int) ($params['dias_uso_mes'] ?? 0)
        );
        $custo = self::calcularCustoEstimado($consumo, (float) ($params['valor_kwh'] ?? 0));

        return ['consumo_kwh' => $consumo, 'custo_estimado' => $custo];
    }

    public static function tensaoValida($tensao): bool
    {
        return in_array((int) $tensao, self::TENSAO_PERMITIDAS, true);
    }
}
