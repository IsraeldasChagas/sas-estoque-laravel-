<?php

namespace App\Support\Rh;

/**
 * Motor de cálculo estimativo de rescisão trabalhista (CLT simplificada).
 * Valores são referência — conferência contábil obrigatória.
 */
final class RhRescisaoCalculo
{
    public const AVISO = 'Estimativa para apoio à decisão. Conferir com contador ou setor responsável antes de efetivar.';

    public const TIPOS_CONTRATO = [
        'experiencia' => 'Contrato de experiência',
        'prazo_indeterminado' => 'Prazo indeterminado',
        'temporario' => 'Temporário',
    ];

    public const TIPOS_RESCISAO = [
        'pedido_demissao' => 'Pedido de demissão',
        'dispensa_sem_justa_causa' => 'Dispensa sem justa causa',
        'dispensa_justa_causa' => 'Dispensa por justa causa',
        'termino_experiencia' => 'Término de contrato de experiência',
        'rescisao_antecipada_empregador' => 'Rescisão antecipada pelo empregador',
        'rescisao_antecipada_empregado' => 'Rescisão antecipada pelo empregado',
        'acordo' => 'Acordo entre empregado e empregador',
    ];

    public const AVISO_PREVIO = [
        'trabalhado' => 'Trabalhado',
        'indenizado' => 'Indenizado',
        'dispensado' => 'Dispensado',
        'nao_cumprido' => 'Não cumprido',
    ];

    public const CENARIOS_COMPARATIVO = [
        'pedido_demissao',
        'dispensa_sem_justa_causa',
        'acordo',
        'termino_experiencia',
        'dispensa_justa_causa',
    ];

    public static function normalizarEntrada(array $d): array
    {
        return [
            'empresa_id' => ! empty($d['empresa_id']) ? (int) $d['empresa_id'] : null,
            'unidade_id' => ! empty($d['unidade_id']) ? (int) $d['unidade_id'] : null,
            'funcionario_id' => ! empty($d['funcionario_id']) ? (int) $d['funcionario_id'] : null,
            'cargo' => trim((string) ($d['cargo'] ?? '')),
            'salario_base' => max(0, (float) ($d['salario_base'] ?? 0)),
            'data_admissao' => $d['data_admissao'] ?? null,
            'data_demissao' => $d['data_demissao'] ?? null,
            'tipo_contrato' => (string) ($d['tipo_contrato'] ?? 'prazo_indeterminado'),
            'tipo_rescisao' => (string) ($d['tipo_rescisao'] ?? 'dispensa_sem_justa_causa'),
            'aviso_previo_tipo' => (string) ($d['aviso_previo_tipo'] ?? 'indenizado'),
            'dias_trabalhados_mes' => max(0, min(31, (int) ($d['dias_trabalhados_mes'] ?? 0))),
            'ferias_vencidas' => max(0, (float) ($d['ferias_vencidas'] ?? 0)),
            'ferias_proporcionais' => max(0, (float) ($d['ferias_proporcionais'] ?? 0)),
            'decimo_terceiro_proporcional' => max(0, (float) ($d['decimo_terceiro_proporcional'] ?? 0)),
            'horas_extras' => max(0, (float) ($d['horas_extras'] ?? 0)),
            'adicionais' => max(0, (float) ($d['adicionais'] ?? 0)),
            'descontos' => max(0, (float) ($d['descontos'] ?? 0)),
            'faltas' => max(0, (float) ($d['faltas'] ?? 0)),
            'adiantamentos' => max(0, (float) ($d['adiantamentos'] ?? 0)),
            'vale_transporte' => max(0, (float) ($d['vale_transporte'] ?? 0)),
            'vale_alimentacao' => max(0, (float) ($d['vale_alimentacao'] ?? 0)),
            'fgts_mensal' => max(0, (float) ($d['fgts_mensal'] ?? 0)),
            'multa_fgts_percentual' => max(0, min(40, (int) ($d['multa_fgts_percentual'] ?? 0))),
            'observacoes' => trim((string) ($d['observacoes'] ?? '')),
        ];
    }

    public static function calcular(array $entradaBruta): array
    {
        $e = self::normalizarEntrada($entradaBruta);
        $sal = $e['salario_base'];
        $tipo = $e['tipo_rescisao'];
        $avisoTipo = $e['aviso_previo_tipo'];
        $salDia = $sal > 0 ? $sal / 30 : 0;

        $saldoSalario = round($salDia * $e['dias_trabalhados_mes'], 2);

        $diasAviso = self::diasAvisoPrevio($e['data_admissao'], $e['data_demissao']);
        $avisoIndenizado = 0.0;
        $avisoDesconto = 0.0;

        if (in_array($tipo, ['dispensa_sem_justa_causa', 'rescisao_antecipada_empregador'], true)) {
            if (in_array($avisoTipo, ['indenizado', 'dispensado'], true)) {
                $avisoIndenizado = round($salDia * $diasAviso, 2);
            }
        } elseif ($tipo === 'acordo') {
            if (in_array($avisoTipo, ['indenizado', 'dispensado'], true)) {
                $avisoIndenizado = round($salDia * $diasAviso * 0.5, 2);
            }
        } elseif (in_array($tipo, ['pedido_demissao', 'rescisao_antecipada_empregado'], true)) {
            if ($avisoTipo === 'nao_cumprido') {
                $avisoDesconto = round($salDia * min(30, $diasAviso), 2);
            }
        }

        $decimo = $e['decimo_terceiro_proporcional'];
        if ($decimo <= 0 && $sal > 0 && $e['data_demissao']) {
            $meses13 = self::meses13Proporcional($e['data_demissao']);
            $decimo = round(($sal / 12) * $meses13, 2);
        }

        $feriasVenc = $e['ferias_vencidas'];
        $feriasProp = $e['ferias_proporcionais'];
        if ($feriasProp <= 0 && $sal > 0 && $e['data_admissao'] && $e['data_demissao']) {
            $mesesFerias = self::mesesFeriasProporcionais($e['data_admissao'], $e['data_demissao']);
            $feriasProp = round(($sal / 12) * $mesesFerias, 2);
        }

        $tercoFerias = round(($feriasVenc + $feriasProp) / 3, 2);

        $proventos = $saldoSalario + $avisoIndenizado + $decimo + $feriasVenc + $feriasProp + $tercoFerias
            + $e['horas_extras'] + $e['adicionais'];

        $descontosFixos = $e['descontos'] + $e['faltas'] + $e['adiantamentos'] + $avisoDesconto
            + $e['vale_transporte'] + $e['vale_alimentacao'];

        $baseInss = max(0, $proventos - $e['horas_extras'] * 0.2);
        $inss = self::estimarInss($baseInss);
        $baseIrrf = max(0, $proventos - $inss);
        $irrf = self::estimarIrrf($baseIrrf);

        $totalDescontos = round($descontosFixos + $inss + $irrf, 2);
        $totalBruto = round($proventos, 2);
        $totalLiquido = round(max(0, $totalBruto - $totalDescontos), 2);

        $multaPct = $e['multa_fgts_percentual'];
        if ($multaPct <= 0) {
            $multaPct = self::multaFgtsPadrao($tipo);
        }
        $fgtsSaldo = $e['fgts_mensal'] > 0
            ? $e['fgts_mensal'] * max(1, self::mesesTrabalhados($e['data_admissao'], $e['data_demissao']))
            : round($sal * 0.08 * max(1, self::mesesTrabalhados($e['data_admissao'], $e['data_demissao'])), 2);
        $fgtsEst = round($fgtsSaldo, 2);
        $multaFgts = round($fgtsEst * ($multaPct / 100), 2);

        $custoEmpresa = round($totalLiquido + $multaFgts + ($tipo !== 'pedido_demissao' ? $fgtsEst * 0.08 : 0), 2);

        return [
            'aviso' => self::AVISO,
            'saldo_salario' => $saldoSalario,
            'aviso_previo_indenizado' => $avisoIndenizado,
            'aviso_previo_desconto' => $avisoDesconto,
            'dias_aviso_previo' => $diasAviso,
            'decimo_terceiro_proporcional' => $decimo,
            'ferias_vencidas' => $feriasVenc,
            'ferias_proporcionais' => $feriasProp,
            'terco_ferias' => $tercoFerias,
            'horas_extras' => $e['horas_extras'],
            'adicionais' => $e['adicionais'],
            'descontos_informados' => $e['descontos'],
            'faltas' => $e['faltas'],
            'adiantamentos' => $e['adiantamentos'],
            'vale_transporte' => $e['vale_transporte'],
            'vale_alimentacao' => $e['vale_alimentacao'],
            'inss_estimado' => $inss,
            'irrf_estimado' => $irrf,
            'fgts_estimado' => $fgtsEst,
            'multa_fgts_percentual' => $multaPct,
            'multa_fgts_valor' => $multaFgts,
            'total_bruto' => $totalBruto,
            'total_descontos' => $totalDescontos,
            'total_liquido' => $totalLiquido,
            'custo_empresa' => $custoEmpresa,
            'necessita_aviso_previo' => self::necessitaAvisoPrevio($tipo),
            'entrada' => $e,
        ];
    }

    public static function compararCenarios(array $entradaBase): array
    {
        $cenarios = [];
        foreach (self::CENARIOS_COMPARATIVO as $tipo) {
            $entrada = array_merge($entradaBase, ['tipo_rescisao' => $tipo]);
            $calc = self::calcular($entrada);
            $cenarios[] = [
                'tipo_cenario' => $tipo,
                'tipo_label' => self::TIPOS_RESCISAO[$tipo] ?? $tipo,
                'total_bruto' => $calc['total_bruto'],
                'total_descontos' => $calc['total_descontos'],
                'total_liquido' => $calc['total_liquido'],
                'custo_empresa' => $calc['custo_empresa'],
                'fgts_estimado' => $calc['fgts_estimado'],
                'multa_fgts_valor' => $calc['multa_fgts_valor'],
                'necessita_aviso_previo' => $calc['necessita_aviso_previo'],
                'detalhes' => $calc,
            ];
        }

        $melhorEmpresa = null;
        $melhorFuncionario = null;
        $minCusto = PHP_FLOAT_MAX;
        $maxLiquido = -1;

        foreach ($cenarios as $c) {
            if ($c['custo_empresa'] < $minCusto) {
                $minCusto = $c['custo_empresa'];
                $melhorEmpresa = $c['tipo_cenario'];
            }
            if ($c['total_liquido'] > $maxLiquido) {
                $maxLiquido = $c['total_liquido'];
                $melhorFuncionario = $c['tipo_cenario'];
            }
        }

        return [
            'aviso' => self::AVISO,
            'cenarios' => $cenarios,
            'melhor_cenario_empresa' => $melhorEmpresa,
            'melhor_cenario_funcionario' => $melhorFuncionario,
        ];
    }

    public static function diasAvisoPrevio(?string $admissao, ?string $demissao): int
    {
        $anos = self::anosCompletos($admissao, $demissao);

        return min(90, 30 + ($anos * 3));
    }

    public static function necessitaAvisoPrevio(string $tipo): bool
    {
        return in_array($tipo, [
            'dispensa_sem_justa_causa',
            'pedido_demissao',
            'rescisao_antecipada_empregador',
            'rescisao_antecipada_empregado',
            'acordo',
        ], true);
    }

    private static function multaFgtsPadrao(string $tipo): int
    {
        return match ($tipo) {
            'dispensa_sem_justa_causa', 'rescisao_antecipada_empregador' => 40,
            'acordo' => 20,
            default => 0,
        };
    }

    private static function anosCompletos(?string $admissao, ?string $demissao): int
    {
        if (! $admissao || ! $demissao) {
            return 0;
        }
        try {
            $a = new \DateTimeImmutable($admissao);
            $d = new \DateTimeImmutable($demissao);

            return max(0, (int) $a->diff($d)->y);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function mesesTrabalhados(?string $admissao, ?string $demissao): int
    {
        if (! $admissao || ! $demissao) {
            return 1;
        }
        try {
            $a = new \DateTimeImmutable($admissao);
            $d = new \DateTimeImmutable($demissao);
            $m = ($d->format('Y') - $a->format('Y')) * 12 + ($d->format('m') - $a->format('m'));

            return max(1, $m);
        } catch (\Throwable) {
            return 1;
        }
    }

    private static function meses13Proporcional(?string $demissao): int
    {
        if (! $demissao) {
            return 0;
        }
        try {
            return (int) (new \DateTimeImmutable($demissao))->format('n');
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function mesesFeriasProporcionais(?string $admissao, ?string $demissao): int
    {
        $total = self::mesesTrabalhados($admissao, $demissao);

        return min(12, $total % 12 ?: 12);
    }

    private static function estimarInss(float $base): float
    {
        if ($base <= 0) {
            return 0;
        }
        $teto = 7786.02;
        $b = min($base, $teto);
        $faixas = [
            [1412.00, 0.075],
            [2666.68, 0.09],
            [4000.03, 0.12],
            [7786.02, 0.14],
        ];
        $prev = 0.0;
        $inss = 0.0;
        foreach ($faixas as [$limite, $aliq]) {
            if ($b <= $prev) {
                break;
            }
            $fatia = min($b, $limite) - $prev;
            if ($fatia > 0) {
                $inss += $fatia * $aliq;
            }
            $prev = $limite;
        }

        return round($inss, 2);
    }

    private static function estimarIrrf(float $base): float
    {
        if ($base <= 2112.00) {
            return 0;
        }
        if ($base <= 2826.65) {
            return round($base * 0.075 - 158.40, 2);
        }
        if ($base <= 3751.05) {
            return round($base * 0.15 - 370.40, 2);
        }
        if ($base <= 4664.68) {
            return round($base * 0.225 - 651.73, 2);
        }

        return round(max(0, $base * 0.275 - 884.96), 2);
    }
}
