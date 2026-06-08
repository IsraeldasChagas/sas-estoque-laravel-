<?php

namespace App\Support\Rh;

/**
 * Motor de cálculo de rescisão no padrão TRCT.
 * Verbas automáticas + overrides manuais (contador).
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

    /** Converte valor monetário sem multiplicar por 10 indevidamente. */
    public static function parseMoeda(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_int($v) || is_float($v)) {
            return max(0, round((float) $v, 2));
        }
        $s = trim((string) $v);
        $s = preg_replace('/[R$\s]/u', '', $s) ?? $s;
        if (preg_match('/^\d+$/', $s)) {
            $digits = (int) $s;

            return $digits >= 1000 ? round($digits / 100, 2) : round($digits, 2);
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        $n = (float) preg_replace('/[^\d.-]/', '', $s);

        return max(0, round($n, 2));
    }

    public static function normalizarEntrada(array $d): array
    {
        $salario = self::parseMoeda($d['salario_base'] ?? 0);
        $avos13 = isset($d['avos_13']) ? (int) $d['avos_13'] : null;
        $avosFer = isset($d['avos_ferias']) ? (int) $d['avos_ferias'] : null;

        if ($avos13 === null && ! empty($d['data_demissao'])) {
            $avos13 = self::sugerirAvos13($d['data_demissao']);
        }
        if ($avosFer === null && ! empty($d['data_admissao']) && ! empty($d['data_demissao'])) {
            $avosFer = self::sugerirAvosFerias($d['data_admissao'], $d['data_demissao']);
        }

        return [
            'empresa_id' => ! empty($d['empresa_id']) ? (int) $d['empresa_id'] : null,
            'unidade_id' => ! empty($d['unidade_id']) ? (int) $d['unidade_id'] : null,
            'funcionario_id' => ! empty($d['funcionario_id']) ? (int) $d['funcionario_id'] : null,
            'cargo' => trim((string) ($d['cargo'] ?? '')),
            'salario_base' => $salario,
            'salario_cadastro' => self::parseMoeda($d['salario_cadastro'] ?? 0),
            'data_admissao' => $d['data_admissao'] ?? null,
            'data_demissao' => $d['data_demissao'] ?? null,
            'data_aviso_previo' => $d['data_aviso_previo'] ?? ($d['data_demissao'] ?? null),
            'tipo_contrato' => (string) ($d['tipo_contrato'] ?? 'prazo_indeterminado'),
            'tipo_rescisao' => (string) ($d['tipo_rescisao'] ?? 'dispensa_sem_justa_causa'),
            'aviso_previo_tipo' => (string) ($d['aviso_previo_tipo'] ?? 'indenizado'),
            'dias_trabalhados_mes' => max(0, min(31, (int) ($d['dias_trabalhados_mes'] ?? 0))),
            'avos_13' => max(0, min(12, (int) ($avos13 ?? 0))),
            'avos_ferias' => max(0, min(12, (int) ($avosFer ?? 0))),
            'ferias_vencidas' => self::parseMoeda($d['ferias_vencidas'] ?? 0),
            'ferias_proporcionais_manual' => self::parseMoeda($d['ferias_proporcionais'] ?? 0),
            'decimo_terceiro_manual' => self::parseMoeda($d['decimo_terceiro_proporcional'] ?? 0),
            'horas_extras_50' => self::parseMoeda($d['horas_extras_50'] ?? 0),
            'horas_extras_60' => self::parseMoeda($d['horas_extras_60'] ?? ($d['horas_extras'] ?? 0)),
            'reflexo_dsr' => self::parseMoeda($d['reflexo_dsr'] ?? 0),
            'gratificacao' => self::parseMoeda($d['gratificacao'] ?? 0),
            'comissoes' => self::parseMoeda($d['comissoes'] ?? 0),
            'outras_verbas' => self::parseMoeda($d['outras_verbas'] ?? ($d['adicionais'] ?? 0)),
            'decimo_aviso_manual' => self::parseMoeda($d['decimo_aviso_previo'] ?? 0),
            'ferias_aviso_manual' => self::parseMoeda($d['ferias_aviso_previo'] ?? 0),
            'adiantamentos' => self::parseMoeda($d['adiantamentos'] ?? 0),
            'adiantamento_13' => self::parseMoeda($d['adiantamento_13'] ?? 0),
            'aviso_previo_descontado' => self::parseMoeda($d['aviso_previo_descontado'] ?? ($d['aviso_previo_desconto'] ?? 0)),
            'faltas' => self::parseMoeda($d['faltas'] ?? 0),
            'dsr_faltas' => self::parseMoeda($d['dsr_faltas'] ?? 0),
            'faltas_dias' => max(0, (int) ($d['faltas_dias'] ?? 0)),
            'inss_salario_manual' => self::parseMoeda($d['inss_salario'] ?? ($d['inss_estimado'] ?? 0)),
            'inss_13_manual' => self::parseMoeda($d['inss_13'] ?? ($d['inss_13_estimado'] ?? 0)),
            'irrf_manual' => self::parseMoeda($d['irrf'] ?? ($d['irrf_estimado'] ?? 0)),
            'irrf_13_manual' => self::parseMoeda($d['irrf_13'] ?? ($d['irrf_13_estimado'] ?? 0)),
            'outros_descontos' => self::parseMoeda($d['outros_descontos'] ?? ($d['descontos'] ?? 0)),
            'vale_transporte' => self::parseMoeda($d['vale_transporte'] ?? 0),
            'vale_alimentacao' => self::parseMoeda($d['vale_alimentacao'] ?? 0),
            'fgts_mensal' => self::parseMoeda($d['fgts_mensal'] ?? 0),
            'multa_fgts_percentual' => max(0, min(40, (int) ($d['multa_fgts_percentual'] ?? 0))),
            'observacoes' => trim((string) ($d['observacoes'] ?? '')),
            'remuneracao_mes_anterior' => self::parseMoeda($d['remuneracao_mes_anterior'] ?? $salario),
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

    /** Validação obrigatória antes do cálculo (18 itens). */
    public static function validarEntrada(array $d): array
    {
        $e = self::normalizarEntrada($d);
        $erros = [];

        if ($e['salario_base'] <= 0) {
            $erros[] = 'Salário base é obrigatório e deve ser maior que zero.';
        }
        if (empty($e['data_admissao'])) {
            $erros[] = 'Data de admissão é obrigatória.';
        }
        if (empty($e['data_aviso_previo'])) {
            $erros[] = 'Data do aviso prévio é obrigatória.';
        }
        if (empty($e['data_demissao'])) {
            $erros[] = 'Data do afastamento (demissão) é obrigatória.';
        }
        if (empty($e['tipo_rescisao'])) {
            $erros[] = 'Tipo de rescisão é obrigatório.';
        }
        if (empty($e['aviso_previo_tipo'])) {
            $erros[] = 'Tipo de aviso prévio é obrigatório.';
        }
        if ($e['dias_trabalhados_mes'] < 0 || $e['dias_trabalhados_mes'] > 31) {
            $erros[] = 'Dias trabalhados no mês deve estar entre 0 e 31.';
        }
        if ($e['avos_13'] < 0 || $e['avos_13'] > 12) {
            $erros[] = 'Avos de 13º salário deve estar entre 0 e 12.';
        }
        if ($e['avos_ferias'] < 0 || $e['avos_ferias'] > 12) {
            $erros[] = 'Avos de férias proporcionais deve estar entre 0 e 12.';
        }

        $alertas = [];
        if ($e['salario_cadastro'] > 0 && $e['salario_base'] >= $e['salario_cadastro'] * 9.5) {
            $alertas[] = 'Atenção: salário informado parece incompatível com o cadastro do funcionário.';
        }

        return ['erros' => $erros, 'alertas' => $alertas, 'entrada' => $e];
    }

    public static function calcular(array $entradaBruta): array
    {
        $val = self::validarEntrada($entradaBruta);
        if (count($val['erros'])) {
            return [
                'ok' => false,
                'erros' => $val['erros'],
                'alertas' => $val['alertas'],
                'aviso' => self::AVISO,
            ];
        }

        $e = $val['entrada'];
        $sal = $e['salario_base'];
        $tipo = $e['tipo_rescisao'];
        $avisoIndenizado = $e['aviso_previo_tipo'] === 'indenizado' ? round($sal, 2) : 0.0;

        // —— Verbas automáticas (fórmulas TRCT) ——
        $verbasAuto = [
            'saldo_salario' => round($sal / 30 * $e['dias_trabalhados_mes'], 2),
            'horas_extras_50' => round($e['horas_extras_50'], 2),
            'horas_extras_60' => round($e['horas_extras_60'], 2),
            'reflexo_dsr' => round($e['reflexo_dsr'], 2),
            'decimo_terceiro_proporcional' => round($sal / 12 * $e['avos_13'], 2),
            'ferias_proporcionais' => round($sal / 12 * $e['avos_ferias'], 2),
            'terco_constitucional' => 0.0,
            'aviso_previo_indenizado' => $avisoIndenizado,
            'decimo_terceiro_aviso_previo' => $avisoIndenizado > 0 ? round($sal / 12, 2) : 0.0,
            'ferias_aviso_previo' => $avisoIndenizado > 0 ? round($sal / 12, 2) : 0.0,
            'ferias_vencidas' => round($e['ferias_vencidas'], 2),
            'gratificacao' => round($e['gratificacao'], 2),
            'comissoes' => round($e['comissoes'], 2),
            'outras_verbas' => round($e['outras_verbas'], 2),
        ];
        $verbasAuto['terco_constitucional'] = round($verbasAuto['ferias_proporcionais'] / 3, 2);
        if ($verbasAuto['ferias_vencidas'] > 0) {
            $verbasAuto['terco_constitucional'] += round($verbasAuto['ferias_vencidas'] / 3, 2);
        }

        // —— Verbas manuais (override do contador) ——
        $verbasManual = [];
        if ($e['decimo_terceiro_manual'] > 0) {
            $verbasManual['decimo_terceiro_proporcional'] = $e['decimo_terceiro_manual'];
        }
        if ($e['ferias_proporcionais_manual'] > 0) {
            $verbasManual['ferias_proporcionais'] = $e['ferias_proporcionais_manual'];
            $verbasManual['terco_constitucional'] = round($e['ferias_proporcionais_manual'] / 3, 2)
                + ($verbasAuto['ferias_vencidas'] > 0 ? round($verbasAuto['ferias_vencidas'] / 3, 2) : 0);
        }
        if ($e['decimo_aviso_manual'] > 0) {
            $verbasManual['decimo_terceiro_aviso_previo'] = $e['decimo_aviso_manual'];
        }
        if ($e['ferias_aviso_manual'] > 0) {
            $verbasManual['ferias_aviso_previo'] = $e['ferias_aviso_manual'];
        }

        $verbasFinais = array_merge($verbasAuto, $verbasManual);
        $horasExtras = $verbasFinais['horas_extras_50'] + $verbasFinais['horas_extras_60'];

        $totalBruto = round(
            $verbasFinais['saldo_salario']
            + $horasExtras
            + $verbasFinais['reflexo_dsr']
            + $verbasFinais['decimo_terceiro_proporcional']
            + $verbasFinais['ferias_proporcionais']
            + $verbasFinais['terco_constitucional']
            + $verbasFinais['aviso_previo_indenizado']
            + $verbasFinais['decimo_terceiro_aviso_previo']
            + $verbasFinais['ferias_aviso_previo']
            + $verbasFinais['ferias_vencidas']
            + $verbasFinais['gratificacao']
            + $verbasFinais['comissoes']
            + $verbasFinais['outras_verbas'],
            2
        );

        // —— Descontos automáticos ——
        $baseInssSal = max(0, $verbasFinais['saldo_salario'] + $horasExtras + $verbasFinais['reflexo_dsr']
            + $verbasFinais['aviso_previo_indenizado'] + $verbasFinais['ferias_vencidas']
            + $verbasFinais['ferias_proporcionais'] + $verbasFinais['terco_constitucional']);
        $baseInss13 = $verbasFinais['decimo_terceiro_proporcional'] + $verbasFinais['decimo_terceiro_aviso_previo'];

        $descontosAuto = [
            'adiantamento_salarial' => round($e['adiantamentos'], 2),
            'aviso_previo_descontado' => round($e['aviso_previo_descontado'], 2),
            'inss_salario' => self::estimarInss($baseInssSal),
            'inss_13' => self::estimarInss($baseInss13),
            'irrf' => self::estimarIrrf(max(0, $totalBruto - self::estimarInss($baseInssSal) - self::estimarInss($baseInss13))),
            'irrf_13' => 0.0,
            'faltas' => round($e['faltas'], 2),
            'dsr_faltas' => round($e['dsr_faltas'], 2),
            'outros_descontos' => round($e['outros_descontos'] + $e['vale_transporte'] + $e['vale_alimentacao'] + $e['adiantamento_13'], 2),
        ];

        // —— Descontos manuais ——
        $descontosManual = [];
        if ($e['inss_salario_manual'] > 0) {
            $descontosManual['inss_salario'] = $e['inss_salario_manual'];
        }
        if ($e['inss_13_manual'] > 0) {
            $descontosManual['inss_13'] = $e['inss_13_manual'];
        }
        if (array_key_exists('irrf', $entradaBruta) || array_key_exists('irrf_estimado', $entradaBruta)) {
            $descontosManual['irrf'] = $e['irrf_manual'];
        }
        if ($e['irrf_13_manual'] > 0) {
            $descontosManual['irrf_13'] = $e['irrf_13_manual'];
        }

        $descontosFinais = array_merge($descontosAuto, $descontosManual);
        $totalDescontos = round(array_sum($descontosFinais), 2);
        $totalLiquido = round(max(0, $totalBruto - $totalDescontos), 2);

        $multaPct = $e['multa_fgts_percentual'] > 0 ? $e['multa_fgts_percentual'] : self::multaFgtsPadrao($tipo);
        $fgtsEst = $e['fgts_mensal'] > 0
            ? round($e['fgts_mensal'] * max(1, self::mesesTrabalhados($e['data_admissao'], $e['data_demissao'])), 2)
            : round($sal * 0.08 * max(1, self::mesesTrabalhados($e['data_admissao'], $e['data_demissao'])), 2);
        $multaFgts = round($fgtsEst * ($multaPct / 100), 2);
        $custoEmpresa = round($totalLiquido + $multaFgts + ($tipo !== 'pedido_demissao' ? $fgtsEst * 0.08 : 0), 2);

        $resultado = [
            'ok' => true,
            'aviso' => self::AVISO,
            'alertas' => $val['alertas'],
            'erros' => [],
            'verbas_automaticas' => $verbasAuto,
            'verbas_manuais' => $verbasManual,
            'verbas_finais' => $verbasFinais,
            'descontos_automaticos' => $descontosAuto,
            'descontos_manuais' => $descontosManual,
            'descontos_finais' => $descontosFinais,
            'saldo_salario' => $verbasFinais['saldo_salario'],
            'aviso_previo_indenizado' => $verbasFinais['aviso_previo_indenizado'],
            'aviso_previo_desconto' => $descontosFinais['aviso_previo_descontado'],
            'decimo_terceiro_proporcional' => $verbasFinais['decimo_terceiro_proporcional'],
            'decimo_aviso_previo' => $verbasFinais['decimo_terceiro_aviso_previo'],
            'ferias_aviso_previo' => $verbasFinais['ferias_aviso_previo'],
            'ferias_vencidas' => $verbasFinais['ferias_vencidas'],
            'ferias_proporcionais' => $verbasFinais['ferias_proporcionais'],
            'terco_ferias' => $verbasFinais['terco_constitucional'],
            'horas_extras' => $horasExtras,
            'horas_extras_50' => $verbasFinais['horas_extras_50'],
            'horas_extras_60' => $verbasFinais['horas_extras_60'],
            'reflexo_dsr' => $verbasFinais['reflexo_dsr'],
            'inss_estimado' => $descontosFinais['inss_salario'],
            'inss_13_estimado' => $descontosFinais['inss_13'],
            'irrf_estimado' => $descontosFinais['irrf'],
            'irrf_13_estimado' => $descontosFinais['irrf_13'],
            'faltas' => $descontosFinais['faltas'],
            'dsr_faltas' => $descontosFinais['dsr_faltas'],
            'adiantamentos' => $descontosFinais['adiantamento_salarial'],
            'total_bruto' => $totalBruto,
            'total_descontos' => $totalDescontos,
            'total_liquido' => $totalLiquido,
            'fgts_estimado' => $fgtsEst,
            'multa_fgts_percentual' => $multaPct,
            'multa_fgts_valor' => $multaFgts,
            'custo_empresa' => $custoEmpresa,
            'avos_13' => $e['avos_13'],
            'avos_ferias' => $e['avos_ferias'],
            'meses_13_avos' => $e['avos_13'],
            'meses_ferias_avos' => $e['avos_ferias'],
            'necessita_aviso_previo' => self::necessitaAvisoPrevio($tipo),
            'entrada' => $e,
        ];

        $resultado['rubricas_trct'] = self::montarRubricasTrct($resultado);
        $resultado['descontos_trct'] = self::montarDescontosTrct($resultado);

        return $resultado;
    }

    public static function montarRubricasTrct(array $calc): array
    {
        $e = $calc['entrada'] ?? [];
        $v = $calc['verbas_finais'] ?? $calc;
        $dias = (int) ($e['dias_trabalhados_mes'] ?? 0);
        $faltasD = (int) ($e['faltas_dias'] ?? 0);
        $m13 = (int) ($calc['avos_13'] ?? 0);
        $mFer = (int) ($calc['avos_ferias'] ?? 0);

        $rub = [];
        $add = function (string $cod, string $desc, float $val) use (&$rub) {
            if (abs($val) >= 0.005) {
                $rub[] = ['codigo' => $cod, 'descricao' => $desc, 'valor' => round($val, 2)];
            }
        };

        $add('50', "Saldo de {$dias} /dias Salário (líquido de {$faltasD} /faltas e DSR)", (float) ($v['saldo_salario'] ?? 0));
        $add('51', 'Comissões', (float) ($v['comissoes'] ?? 0));
        $add('52', 'Gratificação', (float) ($v['gratificacao'] ?? 0));
        $add('56.1', 'Horas Extras 50%', (float) ($v['horas_extras_50'] ?? 0));
        $add('56.2', 'Horas Extras a 60%', (float) ($v['horas_extras_60'] ?? 0));
        $add('59', 'Reflexo do DSR sobre salário variável', (float) ($v['reflexo_dsr'] ?? 0));
        $add('63', "13º Salário proporcional {$m13}/12 avos", (float) ($v['decimo_terceiro_proporcional'] ?? 0));
        $add('65', "Férias proporc. {$mFer}/12 avos", (float) ($v['ferias_proporcionais'] ?? 0));
        $add('66.1', 'Férias venc. período aquisitivo', (float) ($v['ferias_vencidas'] ?? 0));
        $add('68', 'Terço constituc. de férias', (float) ($v['terco_constitucional'] ?? $calc['terco_ferias'] ?? 0));
        $add('69', 'Aviso prévio indenizado', (float) ($v['aviso_previo_indenizado'] ?? 0));
        $add('70', '13º Salário (aviso prévio indenizado)', (float) ($v['decimo_terceiro_aviso_previo'] ?? 0));
        $add('71', 'Férias (aviso prévio indenizado)', (float) ($v['ferias_aviso_previo'] ?? 0));
        $add('95', 'Outras verbas', (float) ($v['outras_verbas'] ?? 0));

        return $rub;
    }

    public static function montarDescontosTrct(array $calc): array
    {
        $d = $calc['descontos_finais'] ?? $calc;
        $desc = [];
        $add = function (string $cod, string $lbl, float $val) use (&$desc) {
            if (abs($val) >= 0.005) {
                $desc[] = ['codigo' => $cod, 'descricao' => $lbl, 'valor' => round($val, 2)];
            }
        };

        $add('101', 'Adiantamento salarial', (float) ($d['adiantamento_salarial'] ?? $calc['adiantamentos'] ?? 0));
        $add('103', 'Aviso prévio descontado', (float) ($d['aviso_previo_descontado'] ?? 0));
        $add('112.1', 'Previdência social', (float) ($d['inss_salario'] ?? $calc['inss_estimado'] ?? 0));
        $add('112.2', 'Prev. social — 13º salário', (float) ($d['inss_13'] ?? $calc['inss_13_estimado'] ?? 0));
        $add('114.1', 'IRRF', (float) ($d['irrf'] ?? $calc['irrf_estimado'] ?? 0));
        $add('114.2', 'IRRF sobre 13º salário', (float) ($d['irrf_13'] ?? $calc['irrf_13_estimado'] ?? 0));
        $add('115.1', 'Faltas não justificadas', (float) ($d['faltas'] ?? 0));
        $add('115.2', 'D.S.R. s/ faltas', (float) ($d['dsr_faltas'] ?? 0));
        $add('99', 'Outros descontos', (float) ($d['outros_descontos'] ?? 0));

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
            if (! ($calc['ok'] ?? true)) {
                continue;
            }
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

    public static function sugerirAvos13(?string $demissao): int
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

    public static function sugerirAvosFerias(?string $admissao, ?string $demissao): int
    {
        if (! $admissao || ! $demissao) {
            return 0;
        }
        try {
            $a = new \DateTimeImmutable($admissao);
            $d = new \DateTimeImmutable($demissao);
            $meses = 0;
            $cur = $a->modify('first day of this month');
            $fim = $d->modify('first day of this month');
            while ($cur <= $fim) {
                $meses++;
                $cur = $cur->modify('+1 month');
            }

            return max(0, min(12, $meses % 12 ?: ($meses > 0 ? 12 : 0)));
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function multaFgtsPadrao(string $tipo): int
    {
        return match ($tipo) {
            'dispensa_sem_justa_causa', 'rescisao_antecipada_empregador' => 40,
            'acordo' => 20,
            default => 0,
        };
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
            return round(max(0, $base * 0.075 - 158.40), 2);
        }
        if ($base <= 3751.05) {
            return round(max(0, $base * 0.15 - 370.40), 2);
        }
        if ($base <= 4664.68) {
            return round(max(0, $base * 0.225 - 651.73), 2);
        }

        return round(max(0, $base * 0.275 - 884.96), 2);
    }
}
