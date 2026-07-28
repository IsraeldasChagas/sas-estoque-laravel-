<?php

/**
 * Gera UM único levantamento (Sistema + Fiscal) em MD e PDF, com logo sem fundo no cabeçalho.
 * Uso: php scripts/gerar_levantamento_sas_pdf.php
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/levantamento_pdf_common.php';

$docsDir = dirname(__DIR__, 2).'/docs';
$sistemaPath = $docsDir.'/LEVANTAMENTO_SISTEMA_SAS_2026-07-28.md';
$fiscalPath = $docsDir.'/LEVANTAMENTO_FISCAL_SAS_2026-07-28.md';
$legendaPath = $docsDir.'/LEGENDA_RODAPE_LEVANTAMENTO.md';
$outMd = $docsDir.'/LEVANTAMENTO_SAS_2026-07-28.md';
$outPdf = $docsDir.'/LEVANTAMENTO_SAS_2026-07-28.pdf';
$rodape = 'LEVANTAMENTO_SAS_2026-07-28';

foreach ([$sistemaPath, $fiscalPath, $legendaPath] as $p) {
    if (! is_readable($p)) {
        fwrite(STDERR, "Arquivo necessário não encontrado: {$p}\n");
        exit(1);
    }
}

function stripRodapeLegenda(string $md): string
{
    $needle = "## Rodapé — Legenda de siglas e termos";
    $pos = strpos($md, $needle);
    if ($pos !== false) {
        $md = substr($md, 0, $pos);
    }

    return rtrim($md)."\n";
}

function stripLeadingTitleBlock(string $md): string
{
    $md = ltrim($md);
    if (! str_starts_with($md, '#')) {
        return $md;
    }
    $parts = preg_split("/\r\n|\n|\r/", $md, 2);
    $rest = $parts[1] ?? '';
    $lines = preg_split("/\r\n|\n|\r/", $rest);
    $out = [];
    $passedDivider = false;
    foreach ($lines as $line) {
        if (! $passedDivider) {
            if (trim($line) === '---') {
                $passedDivider = true;
            }

            continue;
        }
        $out[] = $line;
    }

    return ltrim(implode("\n", $out));
}

$sistemaBody = stripRodapeLegenda(file_get_contents($sistemaPath));
$sistemaBody = stripLeadingTitleBlock($sistemaBody);

$fiscalBody = stripRodapeLegenda(file_get_contents($fiscalPath));
$fiscalBody = stripLeadingTitleBlock($fiscalBody);

$legenda = trim(file_get_contents($legendaPath));

$merged = <<<MD
# Levantamento completo — Grupo Sabor Paraense (SAS-Estoque)

**Data:** 28/07/2026  
**Público:** diretoria e gestão  
**Arquivo:** `LEVANTAMENTO_SAS_2026-07-28` (Sistema + Fiscal)  
**PDF:** logo do grupo no topo; legenda de termos no **Rodapé** (final).

---

# Parte 1 — Sistema (geral)

{$sistemaBody}

---

# Parte 2 — Fiscal

{$fiscalBody}

---

MD;

$merged .= "\n".$legenda."\n";

file_put_contents($outMd, $merged);

try {
    gerarLevantamentoPdf($outMd, $outPdf, $rodape);
    echo "Markdown: {$outMd}\n";
    echo "PDF gerado: {$outPdf}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
