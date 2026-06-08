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

    public const CODIGOS_AFASTAMENTO = [
        'dispensa_sem_justa_causa' => 'SJ2',
        'dispensa_justa_causa' => 'JC2',
        'pedido_demissao' => 'SJ1',
        'termino_experiencia' => 'E1',
        'rescisao_antecipada_empregador' => 'RA1',
        'rescisao_antecipada_empregado' => 'RA2',
        'acordo' => 'AC1',
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
            'data_aviso_previo' => $d['data_aviso_previo'] ?? ($d['data_demissao'] ?? null),
            'remuneracao_mes_anterior' => max(0, (float) ($d['remuneracao_mes_anterior'] ?? $d['salario_base'] ?? 0)),
            'faltas_dias' => max(0, (int) ($d['faltas_dias'] ?? 0)),
            'dsr_faltas' => max(0, (float) ($d['dsr_faltas'] ?? 0)),
            'horas_extras_50' => max(0, (float) ($d['horas_extras_50'] ?? 0)),
            'horas_extras_60' => max(0, (float) ($d['horas_extras_60'] ?? 0)),
            'gratificacao' => max(0, (float) ($d['gratificacao'] ?? 0)),
            'comissoes' => max(0, (float) ($d['comissoes'] ?? 0)),
            'reflexo_dsr' => max(0, (float) ($d['reflexo_dsr'] ?? 0)),
            'adiantamento_13' => max(0, (float) ($d['adiantamento_13'] ?? 0)),
            'decimo_aviso_previo' => max(0, (float) ($d['decimo_aviso_previo'] ?? 0)),
            'ferias_aviso_previo' => max(0, (float) ($d['ferias_aviso_previo'] ?? 0)),
            'codigo_afastamento' => trim((string) ($d['codigo_afastamento'] ?? '')),
            'categoria_trabalhador' => trim((string) ($d['categoria_trabalhador'] ?? '01 - Empregado')),
            'pensao_trct_pct' => max(0, (float) ($d['pensao_trct_pct'] ?? 0)),
            'pensao_fgts_pct' => max(0, (float) ($d['pensao_fgts_pct'] ?? 0)),
            'funcionario_pis' => trim((string) ($d['funcionario_pis'] ?? '')),
            'funcionario_ctps' => trim((string) ($d['funcionario_ctps'] ?? '')),
            'funcionario_cpf' => trim((string) ($d['funcionario_cpf'] ?? '')),
            'funcionario_nascimento' => $d['funcionario_nascimento'] ?? null,
            'funcionario_nome_mae' => trim((string) ($d['funcionario_nome_mae'] ?? '')),
            'funcionario_endereco' => trim((string) ($d['funcionario_endereco'] ?? '')),
            'funcionario_bairro' => trim((string) ($d['funcionario_bairro'] ?? '')),
            'funcionario_municipio' => trim((string) ($d['funcionario_municipio'] ?? '')),
            'funcionario_uf' => trim((string) ($d['funcionario_uf'] ?? '')),
            'funcionario_cep' => trim((string) ($d['funcionario_cep'] ?? '')),
            'empresa_cnpj' => trim((string) ($d['empresa_cnpj'] ?? '')),
            'empresa_razao' => trim((string) ($d['empresa_razao'] ?? '')),
            'empresa_endereco' => trim((string) ($d['empresa_endereco'] ?? '')),
            'empresa_bairro' => trim((string) ($d['empresa_bairro'] ?? '')),
            'empresa_municipio' => trim((string) ($d['empresa_municipio'] ?? '')),
            'empresa_uf' => trim((string) ($d['empresa_uf'] ?? '')),
            'empresa_cep' => trim((string) ($d['empresa_cep'] ?? '')),
            'empresa_cnae' => trim((string) ($d['empresa_cnae'] ?? '')),
            'codigo_sindical' => trim((string) ($d['codigo_sindical'] ?? '')),
            'entidade_sindical' => trim((string) ($d['entidade_sindical'] ?? '')),
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

        $he50 = $e['horas_extras_50'] > 0 ? $e['horas_extras_50'] : 0;
        $he60 = $e['horas_extras_60'] > 0 ? $e['horas_extras_60'] : ($e['horas_extras'] > 0 ? $e['horas_extras'] : 0);
        $reflexoDsr = $e['reflexo_dsr'] > 0 ? $e['reflexo_dsr'] : round(($he50 + $he60) * 0.25, 2);

        $decimoAviso = $e['decimo_aviso_previo'];
        if ($decimoAviso <= 0 && $avisoIndenizado > 0 && $sal > 0) {
            $decimoAviso = round($sal / 12, 2);
        }
        $feriasAviso = $e['ferias_aviso_previo'];
        if ($feriasAviso <= 0 && $avisoIndenizado > 0 && $sal > 0) {
            $feriasAviso = round($sal / 12, 2);
        }

        $proventos = $saldoSalario + $avisoIndenizado + $decimo + $feriasVenc + $feriasProp + $tercoFerias
            + $he50 + $he60 + $reflexoDsr + $e['gratificacao'] + $e['comissoes'] + $decimoAviso + $feriasAviso
            + $e['adicionais'];

        $dsrFaltas = $e['dsr_faltas'] > 0 ? $e['dsr_faltas'] : round($e['faltas'] * 0.5, 2);
        $faltasValor = $e['faltas'] > 0 && $e['dsr_faltas'] <= 0 ? round($e['faltas'] * 0.5, 2) : max(0, $e['faltas'] - $dsrFaltas);

        $descontosFixos = $e['descontos'] + $faltasValor + $dsrFaltas + $e['adiantamentos'] + $e['adiantamento_13']
            + $avisoDesconto + $e['vale_transporte'] + $e['vale_alimentacao'];

        $baseInss = max(0, $proventos - ($he50 + $he60) * 0.2);
        $inssTotal = self::estimarInss($baseInss);
        $inss13 = $decimo > 0 ? round($inssTotal * ($decimo / max(0.01, $proventos)), 2) : 0;
        $inss = round(max(0, $inssTotal - $inss13), 2);
        $baseIrrf = max(0, $proventos - $inssTotal);
        $irrf = self::estimarIrrf($baseIrrf);
        $irrf13 = 0.0;

        $totalDescontos = round($descontosFixos + $inss + $inss13 + $irrf + $irrf13, 2);
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

        $meses13 = self::meses13Proporcional($e['data_demissao']);
        $mesesFerias = self::mesesFeriasProporcionais($e['data_admissao'], $e['data_demissao']);

        $resultado = [
            'aviso' => self::AVISO,
            'saldo_salario' => $saldoSalario,
            'aviso_previo_indenizado' => $avisoIndenizado,
            'aviso_previo_desconto' => $avisoDesconto,
            'dias_aviso_previo' => $diasAviso,
            'decimo_terceiro_proporcional' => $decimo,
            'decimo_aviso_previo' => $decimoAviso,
            'ferias_aviso_previo' => $feriasAviso,
            'ferias_vencidas' => $feriasVenc,
            'ferias_proporcionais' => $feriasProp,
            'terco_ferias' => $tercoFerias,
            'horas_extras' => $he50 + $he60,
            'horas_extras_50' => $he50,
            'horas_extras_60' => $he60,
            'reflexo_dsr' => $reflexoDsr,
            'gratificacao' => $e['gratificacao'],
            'comissoes' => $e['comissoes'],
            'adicionais' => $e['adicionais'],
            'descontos_informados' => $e['descontos'],
            'faltas' => $faltasValor,
            'dsr_faltas' => $dsrFaltas,
            'faltas_dias' => $e['faltas_dias'],
            'adiantamentos' => $e['adiantamentos'],
            'adiantamento_13' => $e['adiantamento_13'],
            'vale_transporte' => $e['vale_transporte'],
            'vale_alimentacao' => $e['vale_alimentacao'],
            'inss_estimado' => $inss,
            'inss_13_estimado' => $inss13,
            'irrf_estimado' => $irrf,
            'irrf_13_estimado' => $irrf13,
            'fgts_estimado' => $fgtsEst,
            'multa_fgts_percentual' => $multaPct,
            'multa_fgts_valor' => $multaFgts,
            'total_bruto' => $totalBruto,
            'total_descontos' => $totalDescontos,
            'total_liquido' => $totalLiquido,
            'custo_empresa' => $custoEmpresa,
            'necessita_aviso_previo' => self::necessitaAvisoPrevio($tipo),
            'meses_13_avos' => $meses13,
            'meses_ferias_avos' => $mesesFerias,
            'entrada' => $e,
        ];

        $resultado['rubricas_trct'] = self::montarRubricasTrct($resultado);
        $resultado['descontos_trct'] = self::montarDescontosTrct($resultado);

        return $resultado;
    }

    public static function montarRubricasTrct(array $calc): array
    {
        $e = $calc['entrada'] ?? [];
        $dias = (int) ($e['dias_trabalhados_mes'] ?? 0);
        $faltasD = (int) ($e['faltas_dias'] ?? 0);
        $m13 = (int) ($calc['meses_13_avos'] ?? 0);
        $mFer = (int) ($calc['meses_ferias_avos'] ?? 0);

        $rub = [];
        $add = function (string $cod, string $desc, float $val) use (&$rub) {
            if (abs($val) >= 0.005) {
                $rub[] = ['codigo' => $cod, 'descricao' => $desc, 'valor' => round($val, 2)];
            }
        };

        $add('50', "Saldo de {$dias} /dias Salário (líquido de {$faltasD} /faltas e DSR)", (float) ($calc['saldo_salario'] ?? 0));
        $add('51', 'Comissões', (float) ($calc['comissoes'] ?? 0));
        $add('52', 'Gratificação', (float) ($calc['gratificacao'] ?? 0));
        $add('56.1', 'Horas Extras 50%', (float) ($calc['horas_extras_50'] ?? 0));
        $add('56.2', 'Horas Extras a 60%', (float) ($calc['horas_extras_60'] ?? 0));
        $add('59', 'Reflexo do DSR sobre salário variável', (float) ($calc['reflexo_dsr'] ?? 0));
        $add('63', "13º Salário proporcional {$m13}/12 avos", (float) ($calc['decimo_terceiro_proporcional'] ?? 0));
        $add('65', "Férias proporc. {$mFer}/12 avos", (float) ($calc['ferias_proporcionais'] ?? 0));
        $add('66.1', 'Férias venc. período aquisitivo', (float) ($calc['ferias_vencidas'] ?? 0));
        $add('68', 'Terço constituc. de férias', (float) ($calc['terco_ferias'] ?? 0));
        $add('69', 'Aviso prévio indenizado de '.(int) ($calc['dias_aviso_previo'] ?? 0).' dias', (float) ($calc['aviso_previo_indenizado'] ?? 0));
        $add('70', '13º Salário (aviso prévio indenizado)', (float) ($calc['decimo_aviso_previo'] ?? 0));
        $add('71', 'Férias (aviso prévio indenizado)', (float) ($calc['ferias_aviso_previo'] ?? 0));
        $add('95', 'Outras verbas / adicionais', (float) ($calc['adicionais'] ?? 0));

        return $rub;
    }

    public static function montarDescontosTrct(array $calc): array
    {
        $desc = [];
        $add = function (string $cod, string $lbl, float $val) use (&$desc) {
            if (abs($val) >= 0.005) {
                $desc[] = ['codigo' => $cod, 'descricao' => $lbl, 'valor' => round($val, 2)];
            }
        };

        $add('101', 'Adiantamento salarial', (float) ($calc['adiantamentos'] ?? 0));
        $add('102', 'Adiantamento de 13º salário', (float) ($calc['adiantamento_13'] ?? 0));
        $add('103', 'Aviso prévio indenizado (desconto)', (float) ($calc['aviso_previo_desconto'] ?? 0));
        $add('112.1', 'Previdência social', (float) ($calc['inss_estimado'] ?? 0));
        $add('112.2', 'Prev. social — 13º salário', (float) ($calc['inss_13_estimado'] ?? 0));
        $add('114.1', 'IRRF', (float) ($calc['irrf_estimado'] ?? 0));
        $add('114.2', 'IRRF sobre 13º salário', (float) ($calc['irrf_13_estimado'] ?? 0));
        $add('115.1', 'Faltas não justificadas', (float) ($calc['faltas'] ?? 0));
        $add('115.2', 'D.S.R. s/ faltas', (float) ($calc['dsr_faltas'] ?? 0));
        $add('99', 'Outros descontos informados', (float) ($calc['descontos_informados'] ?? 0)
            + (float) ($calc['vale_transporte'] ?? 0) + (float) ($calc['vale_alimentacao'] ?? 0));

        return $desc;
    }

    public static function codigoAfastamento(string $tipo): string
    {
        return self::CODIGOS_AFASTAMENTO[$tipo] ?? 'SJ2';
    }

    public static function labelCausaAfastamento(string $tipo): string
    {
        return match ($tipo) {
            'dispensa_sem_justa_causa' => 'Despedida sem justa causa, pelo empregador',
            'dispensa_justa_causa' => 'Despedida por justa causa, pelo empregador',
            'pedido_demissao' => 'Pedido de demissão, pelo empregado',
            'termino_experiencia' => 'Término de contrato de experiência',
            'rescisao_antecipada_empregador' => 'Rescisão antecipada pelo empregador',
            'rescisao_antecipada_empregado' => 'Rescisão antecipada pelo empregado',
            'acordo' => 'Rescisão por acordo entre as partes',
            default => self::TIPOS_RESCISAO[$tipo] ?? $tipo,
        };
    }

    public static function labelTipoContratoTrct(string $tipo): string
    {
        return match ($tipo) {
            'experiencia' => '2 - Contrato de trabalho por prazo determinado (experiência)',
            'temporario' => '3 - Contrato de trabalho temporário',
            default => '1 - Contrato de trabalho por prazo indeterminado',
        };
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
