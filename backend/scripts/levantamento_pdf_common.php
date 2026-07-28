<?php

declare(strict_types=1);

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

function levantamentoLogoDataUri(): string
{
    $backendRoot = dirname(__DIR__);
    $projectRoot = dirname($backendRoot);
    $candidates = [
        $projectRoot.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'imagens'.DIRECTORY_SEPARATOR.'logosemfundo.png',
        $projectRoot.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'imagens'.DIRECTORY_SEPARATOR.'logo-sem-fundo.png',
        $backendRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'imagens'.DIRECTORY_SEPARATOR.'logosemfundo.png',
        $projectRoot.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'imagens'.DIRECTORY_SEPARATOR.'logo.png',
    ];
    foreach ($candidates as $path) {
        if (! is_readable($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            continue;
        }
        $mime = 'image/png';
        if (str_ends_with(strtolower($path), '.jpg') || str_ends_with(strtolower($path), '.jpeg')) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($raw);
    }

    return '';
}

function levantamentoPdfHeaderHtml(): string
{
    $logo = levantamentoLogoDataUri();
    $img = $logo !== ''
        ? '<img src="'.htmlspecialchars($logo, ENT_QUOTES, 'UTF-8').'" alt="Grupo Sabor Paraense" class="pdf-logo" />'
        : '';
    $brand = '<div class="pdf-brand-text"><strong>Grupo Sabor Paraense</strong><span>SAS-Estoque</span></div>';

    return '<table class="pdf-page-header" width="100%"><tr>'
        .'<td class="pdf-logo-cell" width="130">'.$img.'</td>'
        .'<td class="pdf-brand-cell">'.$brand.'</td>'
        .'</tr></table>';
}

function gerarLevantamentoPdf(string $mdPath, string $pdfPath, string $rodapeNomeDocumento): void
{
    if (! is_readable($mdPath)) {
        throw new RuntimeException("Markdown não encontrado: {$mdPath}");
    }

    $body = levantamentoPdfHeaderHtml().markdownToHtml(file_get_contents($mdPath));

    $fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page { margin: 72px 28px 52px 28px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.35; color: #111; margin: 0; }
.pdf-page-header {
  position: fixed; top: -58px; left: 0; right: 0;
  border-bottom: 1px solid #cbd5e1;
}
.pdf-logo-cell { vertical-align: middle; padding: 0 0 4px 0; }
.pdf-brand-cell { vertical-align: middle; padding: 0 0 4px 8px; }
.pdf-logo { max-height: 46px; max-width: 120px; }
.pdf-brand-text strong { display: block; font-size: 11pt; color: #1e3a8a; line-height: 1.2; }
.pdf-brand-text span { display: block; font-size: 8pt; color: #64748b; }
h1 { font-size: 16pt; border-bottom: 2px solid #2563eb; padding-bottom: 6px; margin-top: 8px; }
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

    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
    $linha1 = $rodapeNomeDocumento;
    $linha2 = 'Legenda de siglas e termos: ultima secao deste documento (Rodape)';
    $canvas->page_text(40, 808, $linha1, $font, 7, [0.25, 0.25, 0.35]);
    $canvas->page_text(40, 818, $linha2, $font, 6.5, [0.4, 0.4, 0.45]);
    $canvas->page_text(480, 818, 'Pag. {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.35, 0.35, 0.4]);

    file_put_contents($pdfPath, $dompdf->output());
}
