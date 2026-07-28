<?php

/**
 * Gera PDF a partir de docs/LEVANTAMENTO_FISCAL_SAS_2026-07-28.md
 * Uso: php scripts/gerar_levantamento_fiscal_pdf.php
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$mdPath = dirname(__DIR__, 2).'/docs/LEVANTAMENTO_FISCAL_SAS_2026-07-28.md';
$pdfPath = dirname(__DIR__, 2).'/docs/LEVANTAMENTO_FISCAL_SAS_2026-07-28.pdf';

if (! is_readable($mdPath)) {
    fwrite(STDERR, "Markdown não encontrado: {$mdPath}\n");
    exit(1);
}

function markdownToHtml(string $md): string
{
    $lines = preg_split("/\r\n|\n|\r/", $md);
    $html = '';
    $inCode = false;
    $inTable = false;
    $tableHeaderDone = false;

    foreach ($lines as $line) {
        if (str_starts_with($line, '```')) {
            if ($inCode) {
                $html .= '</pre>';
                $inCode = false;
            } else {
                $html .= '<pre class="code">';
                $inCode = true;
            }

            continue;
        }
        if ($inCode) {
            $html .= htmlspecialchars($line, ENT_QUOTES, 'UTF-8')."\n";

            continue;
        }

        if (preg_match('/^# (.+)$/', $line, $m)) {
            $html .= '<h1>'.htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8').'</h1>';

            continue;
        }
        if (preg_match('/^## (.+)$/', $line, $m)) {
            $html .= '<h2>'.htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8').'</h2>';

            continue;
        }
        if (preg_match('/^### (.+)$/', $line, $m)) {
            $html .= '<h3>'.htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8').'</h3>';

            continue;
        }

        if (str_starts_with(trim($line), '|')) {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (count($cells) < 2) {
                continue;
            }
            if (preg_match('/^[-:\s|]+$/', $line)) {
                continue;
            }
            if (! $inTable) {
                $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
                $inTable = true;
                $tableHeaderDone = false;
            }
            $tag = ! $tableHeaderDone ? 'th' : 'td';
            $tableHeaderDone = true;
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<'.$tag.'>'.inlineMd(htmlspecialchars($cell, ENT_QUOTES, 'UTF-8')).'</'.$tag.'>';
            }
            $html .= '</tr>';

            continue;
        }
        if ($inTable) {
            $html .= '</table>';
            $inTable = false;
            $tableHeaderDone = false;
        }

        if (preg_match('/^- (.+)$/', $line, $m)) {
            $html .= '<li>'.inlineMd(htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8')).'</li>';

            continue;
        }
        if (preg_match('/^\d+\. (.+)$/', $line, $m)) {
            $html .= '<li>'.inlineMd(htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8')).'</li>';

            continue;
        }

        if (trim($line) === '---') {
            $html .= '<hr/>';

            continue;
        }

        if (trim($line) === '') {
            continue;
        }

        $html .= '<p>'.inlineMd(htmlspecialchars($line, ENT_QUOTES, 'UTF-8')).'</p>';
    }

    if ($inTable) {
        $html .= '</table>';
    }
    if ($inCode) {
        $html .= '</pre>';
    }

    return $html;
}

function inlineMd(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text) ?? $text;

    return $text;
}

$body = markdownToHtml(file_get_contents($mdPath));

$fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.35; color: #111; margin: 28px; }
h1 { font-size: 16pt; border-bottom: 2px solid #2563eb; padding-bottom: 6px; }
h2 { font-size: 12pt; margin-top: 16px; color: #1e40af; }
h3 { font-size: 11pt; margin-top: 12px; }
table { border-collapse: collapse; margin: 8px 0 12px; font-size: 9pt; }
th { background: #eff6ff; text-align: left; }
pre.code { background: #f3f4f6; padding: 8px; font-size: 8pt; white-space: pre-wrap; }
code { background: #f3f4f6; padding: 1px 3px; font-size: 9pt; }
li { margin: 2px 0 2px 18px; }
hr { border: none; border-top: 1px solid #ccc; margin: 16px 0; }
p { margin: 4px 0 8px; }
</style></head><body>'.$body.'</body></html>';

$dompdf = new Dompdf\Dompdf();
$opts = $dompdf->getOptions();
$opts->set('isRemoteEnabled', false);
$opts->set('defaultFont', 'DejaVu Sans');
$dompdf->setOptions($opts);
$dompdf->loadHtml($fullHtml, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

file_put_contents($pdfPath, $dompdf->output());
echo "PDF gerado: {$pdfPath}\n";
