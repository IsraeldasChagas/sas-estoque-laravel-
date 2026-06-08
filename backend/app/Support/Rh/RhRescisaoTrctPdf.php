<?php

namespace App\Support\Rh;

/**
 * Gera PDF no formato TRCT (Termo de Rescisão do Contrato de Trabalho).
 * Layout inspirado no modelo oficial — valores são estimativas do sistema.
 */
final class RhRescisaoTrctPdf
{
    public static function render(object $rescisao, array $calc, ?object $funcionario = null, ?object $unidade = null, string $via = 'completo'): string
    {
        $ctx = self::montarContexto($rescisao, $calc, $funcionario, $unidade);
        $h = fn ($s) => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
        $m = fn ($v) => 'R$ '.number_format((float) ($v ?? 0), 2, ',', '.');
        $d = fn ($v) => self::fmtData($v);

        $rub = $calc['rubricas_trct'] ?? RhRescisaoCalculo::montarRubricasTrct($calc);
        $desc = $calc['descontos_trct'] ?? [];

        $verbasHtml = self::tabelaTresColunas($rub, $m, $h);
        $descHtml = self::tabelaTresColunas($desc, $m, $h, true);

        $pagina1 = self::paginaTrct($ctx, $h, $m, $d, $verbasHtml, $descHtml, $calc);
        $pagina2 = self::paginaQuitacao($ctx, $h, $m, $d, $calc);
        $pagina3 = self::paginaHomologacao($ctx, $h, $m, $d, $calc);

        $body = match ($via) {
            'funcionario' => $pagina1.'<div class="page-break"></div>'.$pagina2,
            'quitacao' => $pagina2,
            default => $pagina1.'<div class="page-break"></div>'.$pagina2.'<div class="page-break"></div>'.$pagina3,
        };

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"/>'
            .self::css().'</head><body>'.$body.'</body></html>';
    }

    public static function montarContexto(object $rescisao, array $calc, ?object $funcionario, ?object $unidade): array
    {
        $e = $calc['entrada'] ?? [];
        $u = $unidade ? (array) $unidade : [];
        $f = $funcionario ? (array) $funcionario : [];

        $cnpj = $e['empresa_cnpj'] ?? $u['cnpj'] ?? '';
        $razao = $e['empresa_razao'] ?? $u['nome'] ?? ($rescisao->unidade_nome ?? '');
        $empEnd = $e['empresa_endereco'] ?? $u['endereco'] ?? '';

        return [
            'empresa_cnpj' => $cnpj,
            'empresa_razao' => $razao,
            'empresa_endereco' => $empEnd,
            'empresa_bairro' => $e['empresa_bairro'] ?? '',
            'empresa_municipio' => $e['empresa_municipio'] ?? '',
            'empresa_uf' => $e['empresa_uf'] ?? '',
            'empresa_cep' => $e['empresa_cep'] ?? '',
            'empresa_cnae' => $e['empresa_cnae'] ?? '',
            'funcionario_pis' => $e['funcionario_pis'] ?? '',
            'funcionario_nome' => $f['nome_completo'] ?? $rescisao->funcionario_nome ?? '',
            'funcionario_endereco' => $e['funcionario_endereco'] ?? '',
            'funcionario_bairro' => $e['funcionario_bairro'] ?? '',
            'funcionario_municipio' => $e['funcionario_municipio'] ?? '',
            'funcionario_uf' => $e['funcionario_uf'] ?? '',
            'funcionario_cep' => $e['funcionario_cep'] ?? '',
            'funcionario_ctps' => $f['ctps'] ?? $e['funcionario_ctps'] ?? '',
            'funcionario_cpf' => $f['cpf'] ?? $e['funcionario_cpf'] ?? '',
            'funcionario_nascimento' => $f['data_nascimento'] ?? $e['funcionario_nascimento'] ?? '',
            'funcionario_mae' => $e['funcionario_nome_mae'] ?? '',
            'tipo_contrato_label' => RhRescisaoCalculo::labelTipoContratoTrct($e['tipo_contrato'] ?? $rescisao->tipo_contrato ?? ''),
            'causa_afastamento' => RhRescisaoCalculo::labelCausaAfastamento($e['tipo_rescisao'] ?? $rescisao->tipo_rescisao ?? ''),
            'data_admissao' => $e['data_admissao'] ?? $rescisao->data_admissao ?? '',
            'data_aviso_previo' => $e['data_aviso_previo'] ?? $e['data_demissao'] ?? $rescisao->data_demissao ?? '',
            'data_afastamento' => $e['data_demissao'] ?? $rescisao->data_demissao ?? '',
            'codigo_afastamento' => $e['codigo_afastamento'] ?? RhRescisaoCalculo::codigoAfastamento($e['tipo_rescisao'] ?? $rescisao->tipo_rescisao ?? ''),
            'remuneracao_mes_ant' => $e['remuneracao_mes_anterior'] ?? $e['salario_base'] ?? $rescisao->salario_base ?? 0,
            'pensao_trct_pct' => $e['pensao_trct_pct'] ?? 0,
            'pensao_fgts_pct' => $e['pensao_fgts_pct'] ?? 0,
            'categoria_trabalhador' => $e['categoria_trabalhador'] ?? '01 - Empregado',
            'codigo_sindical' => $e['codigo_sindical'] ?? '',
            'entidade_sindical' => $e['entidade_sindical'] ?? '',
            'cargo' => $e['cargo'] ?? $rescisao->cargo ?? '',
            'dias_trabalhados' => $e['dias_trabalhados_mes'] ?? $rescisao->dias_trabalhados_mes ?? 0,
            'faltas_dias' => $e['faltas_dias'] ?? 0,
        ];
    }

    private static function paginaTrct(array $ctx, callable $h, callable $m, callable $d, string $verbas, string $desc, array $calc): string
    {
        $aviso = RhRescisaoCalculo::AVISO;

        return '<div class="sheet">'
            .'<div class="titulo-principal">TERMO DE RESCISÃO DO CONTRATO DE TRABALHO</div>'
            .'<div class="sec-titulo">IDENTIFICAÇÃO DO EMPREGADOR</div>'
            .self::gridCampos([
                ['01 CNPJ/CEI', self::fmtCnpj($ctx['empresa_cnpj'])],
                ['02 Razão Social/Nome', $ctx['empresa_razao']],
                ['03 Endereço', $ctx['empresa_endereco']],
                ['04 Bairro', $ctx['empresa_bairro']],
                ['05 Município', $ctx['empresa_municipio']],
                ['06 UF', $ctx['empresa_uf']],
                ['07 CEP', $ctx['empresa_cep']],
                ['08 CNAE', $ctx['empresa_cnae']],
                ['09 CNPJ/CEI Tomador/Obra', ''],
            ], $h)
            .'<div class="sec-titulo">IDENTIFICAÇÃO DO TRABALHADOR</div>'
            .self::gridCampos([
                ['10 PIS/PASEP', $ctx['funcionario_pis']],
                ['11 Nome', $ctx['funcionario_nome']],
                ['12 Endereço', $ctx['funcionario_endereco']],
                ['13 Bairro', $ctx['funcionario_bairro']],
                ['14 Município', $ctx['funcionario_municipio']],
                ['15 UF', $ctx['funcionario_uf']],
                ['16 CEP', $ctx['funcionario_cep']],
                ['17 CTPS (nº, série, UF)', $ctx['funcionario_ctps']],
                ['18 CPF', self::fmtCpf($ctx['funcionario_cpf'])],
                ['19 Data de Nascimento', $d($ctx['funcionario_nascimento'])],
                ['20 Nome da Mãe', $ctx['funcionario_mae']],
            ], $h)
            .'<div class="sec-titulo">DADOS DO CONTRATO</div>'
            .self::gridCampos([
                ['21 Tipo de Contrato', $ctx['tipo_contrato_label']],
                ['22 Causa do Afastamento', $ctx['causa_afastamento']],
                ['23 Remuneração Mês Ant.', $m($ctx['remuneracao_mes_ant'])],
                ['24 Data de Admissão', $d($ctx['data_admissao'])],
                ['25 Data do Aviso Prévio', $d($ctx['data_aviso_previo'])],
                ['26 Data do Afastamento', $d($ctx['data_afastamento'])],
                ['27 Cód. Afastamento', $ctx['codigo_afastamento']],
                ['28 Pensão Alim. (%) TRCT', number_format((float) $ctx['pensao_trct_pct'], 2, ',', '.').' %'],
                ['29 Pensão Alim. (%) FGTS', number_format((float) $ctx['pensao_fgts_pct'], 2, ',', '.').' %'],
                ['30 Categoria do Trabalhador', $ctx['categoria_trabalhador']],
                ['31 Código Sindical', $ctx['codigo_sindical']],
                ['32 CNPJ e Nome da Entidade Sindical', $ctx['entidade_sindical']],
            ], $h)
            .'<div class="sec-titulo">DISCRIMINAÇÃO DAS VERBAS RESCISÓRIAS</div>'
            .'<table class="trct"><thead><tr><th colspan="3">VERBAS RESCISÓRIAS</th></tr></thead><tbody>'.$verbas.'</tbody></table>'
            .'<div class="totais"><strong>TOTAL BRUTO</strong> '.$m($calc['total_bruto'] ?? 0).'</div>'
            .'<div class="sec-titulo">DEDUÇÕES</div>'
            .'<table class="trct"><thead><tr><th colspan="3">DEDUÇÕES</th></tr></thead><tbody>'.$desc.'</tbody></table>'
            .'<div class="totais"><strong>TOTAL DEDUÇÕES</strong> '.$m($calc['total_descontos'] ?? 0).'</div>'
            .'<div class="totais liquido"><strong>VALOR LÍQUIDO</strong> '.$m($calc['total_liquido'] ?? 0).'</div>'
            .'<p class="aviso">'.$h($aviso).'</p>'
            .'</div>';
    }

    private static function paginaQuitacao(array $ctx, callable $h, callable $m, callable $d, array $calc): string
    {
        $liq = $m($calc['total_liquido'] ?? 0);
        $texto = 'No dia ___/___/____ foi realizado, nos termos da lei 13.467/2017, art. 477, § 4°, o efetivo pagamento das verbas rescisórias especificadas no corpo do TRCT, no valor líquido de '.$liq.', o qual, devidamente rubricado pelas partes, é parte integrante do presente Termo de Quitação.';

        return '<div class="sheet">'
            .'<div class="titulo-principal">TERMO DE QUITAÇÃO DE RESCISÃO DE CONTRATO DE TRABALHO</div>'
            .self::resumoPartes($ctx, $h, $m, $d)
            .'<p class="texto-legal">'.$h($texto).'</p>'
            .'<table class="assin"><tr>'
            .'<td><div class="ass-lbl">150 Assinatura do empregador ou preposto</div><div class="ass-linha"></div><div class="ass-emp">'.$h($ctx['empresa_razao']).'</div><div class="ass-emp">CNPJ: '.$h(self::fmtCnpj($ctx['empresa_cnpj'])).'</div></td>'
            .'<td><div class="ass-lbl">151 Assinatura do trabalhador</div><div class="ass-linha"></div></td>'
            .'<td><div class="ass-lbl">152 Assinatura do responsável legal</div><div class="ass-linha"></div></td>'
            .'</tr></table>'
            .'<p class="via-func">Via do trabalhador — conferir valores com o TRCT e guardar assinado.</p>'
            .'</div>';
    }

    private static function paginaHomologacao(array $ctx, callable $h, callable $m, callable $d, array $calc): string
    {
        $liq = $m($calc['total_liquido'] ?? 0);

        return '<div class="sheet">'
            .'<div class="titulo-principal">TERMO DE HOMOLOGAÇÃO DE RESCISÃO DE CONTRATO DE TRABALHO</div>'
            .'<p class="texto-legal">Foi prestada a assistência na rescisão do contrato de trabalho, sendo comprovado neste ato o efetivo pagamento das verbas rescisórias especificadas no corpo do TRCT, no valor líquido de '.$liq.', o qual, devidamente rubricado pelas partes, é integrante do presente Termo de Homologação.</p>'
            .'<p class="texto-legal">As partes assistidas no presente ato de rescisão contratual foram identificadas como legítimas conforme previsto na Instrução Normativa/SRT nº 15/2010.</p>'
            .self::resumoPartes($ctx, $h, $m, $d)
            .'<table class="assin"><tr>'
            .'<td><div class="ass-lbl">153 Carimbo e assinatura do assistente</div><div class="ass-linha"></div></td>'
            .'<td><div class="ass-lbl">154 Nome do órgão homologador</div><div class="ass-linha"></div></td>'
            .'</tr></table>'
            .'<div class="campo-livre"><strong>155 Ressalvas</strong><div class="linha-livre"></div></div>'
            .'</div>';
    }

    private static function resumoPartes(array $ctx, callable $h, callable $m, callable $d): string
    {
        return '<table class="resumo"><tr><td width="50%">'
            .'<div><strong>EMPREGADOR</strong></div>'
            .'<div>01 CNPJ: '.$h(self::fmtCnpj($ctx['empresa_cnpj'])).'</div>'
            .'<div>02 Razão: '.$h($ctx['empresa_razao']).'</div>'
            .'</td><td width="50%">'
            .'<div><strong>TRABALHADOR</strong></div>'
            .'<div>11 Nome: '.$h($ctx['funcionario_nome']).'</div>'
            .'<div>18 CPF: '.$h(self::fmtCpf($ctx['funcionario_cpf'])).'</div>'
            .'<div>17 CTPS: '.$h($ctx['funcionario_ctps']).'</div>'
            .'</td></tr><tr><td colspan="2">'
            .'<div><strong>CONTRATO</strong></div>'
            .'<div>22 '.$h($ctx['causa_afastamento']).' &nbsp;|&nbsp; 24 Admissão: '.$d($ctx['data_admissao']).' &nbsp;|&nbsp; 26 Afastamento: '.$d($ctx['data_afastamento']).' &nbsp;|&nbsp; 27 '.$h($ctx['codigo_afastamento']).'</div>'
            .'</td></tr></table>';
    }

    private static function tabelaTresColunas(array $itens, callable $m, callable $h, bool $desconto = false): string
    {
        $chunks = array_chunk($itens, 3);
        $html = '';
        foreach ($chunks as $row) {
            $html .= '<tr>';
            for ($i = 0; $i < 3; $i++) {
                if (isset($row[$i])) {
                    $r = $row[$i];
                    $html .= '<td><span class="rub-cod">'.$h($r['codigo'] ?? '').'</span> '
                        .'<span class="rub-desc">'.$h($r['descricao'] ?? '').'</span><br/>'
                        .'<strong>'.$m($r['valor'] ?? 0).'</strong></td>';
                } else {
                    $html .= '<td></td>';
                }
            }
            $html .= '</tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="3" style="text-align:center;color:#666">—</td></tr>';
        }

        return $html;
    }

    private static function gridCampos(array $campos, callable $h): string
    {
        $html = '<table class="campos">';
        $chunks = array_chunk($campos, 2);
        foreach ($chunks as $par) {
            $html .= '<tr>';
            foreach ($par as $c) {
                $html .= '<td width="50%"><span class="lbl">'.$h($c[0]).'</span><div class="val">'.$h($c[1]).'</div></td>';
            }
            if (count($par) === 1) {
                $html .= '<td width="50%"></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private static function css(): string
    {
        return '<style>
@page { margin: 10mm 8mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #111; line-height: 1.25; }
.page-break { page-break-before: always; }
.sheet { width: 100%; }
.titulo-principal { text-align: center; font-weight: bold; font-size: 9pt; margin: 0 0 8px; border: 1px solid #111; padding: 4px; }
.sec-titulo { font-weight: bold; font-size: 7.5pt; background: #e8e8e8; border: 1px solid #111; padding: 3px 5px; margin-top: 6px; }
table.campos { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
table.campos td { border: 1px solid #111; padding: 3px 5px; vertical-align: top; }
.lbl { font-size: 6.5pt; color: #333; display: block; }
.val { font-size: 7.5pt; min-height: 12px; }
table.trct { width: 100%; border-collapse: collapse; margin-top: 4px; }
table.trct th, table.trct td { border: 1px solid #111; padding: 4px; vertical-align: top; width: 33%; }
.rub-cod { font-weight: bold; }
.rub-desc { font-size: 6.5pt; }
.totais { text-align: right; border: 1px solid #111; padding: 5px 8px; margin-top: 4px; font-size: 8pt; }
.totais.liquido { background: #f0f4ff; font-size: 9pt; }
.aviso { font-size: 6.5pt; color: #5d4037; background: #fff8e1; border: 1px solid #ffe082; padding: 5px; margin-top: 8px; }
.texto-legal { font-size: 7pt; text-align: justify; margin: 8px 0; }
table.resumo { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 7pt; }
table.resumo td { border: 1px solid #111; padding: 5px; vertical-align: top; }
table.assin { width: 100%; border-collapse: collapse; margin-top: 20px; }
table.assin td { border: 1px solid #111; padding: 8px; vertical-align: bottom; height: 70px; width: 33%; }
.ass-lbl { font-size: 6.5pt; margin-bottom: 30px; }
.ass-linha { border-bottom: 1px solid #111; height: 1px; margin-bottom: 4px; }
.ass-emp { font-size: 6.5pt; }
.via-func { font-size: 7pt; font-weight: bold; text-align: center; margin-top: 12px; }
.campo-livre { margin-top: 10px; }
.linha-livre { border-bottom: 1px solid #111; height: 40px; margin-top: 6px; }
</style>';
    }

    private static function fmtData(?string $v): string
    {
        if (! $v) {
            return '';
        }
        try {
            return (new \DateTimeImmutable($v))->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $v;
        }
    }

    private static function fmtCpf(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        if (strlen($d) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        }

        return (string) $v;
    }

    private static function fmtCnpj(?string $v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        if (strlen($d) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        }

        return (string) $v;
    }
}
