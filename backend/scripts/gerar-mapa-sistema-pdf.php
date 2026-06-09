<?php

/**
 * Gera PDF com mapa UML de todos os módulos do SAS-Estoque.
 * Uso: php scripts/gerar-mapa-sistema-pdf.php
 */

require __DIR__.'/../vendor/autoload.php';

use App\Support\SasMapaSistemaPdf;

$html = SasMapaSistemaPdf::renderHtml();

$dompdf = new Dompdf\Dompdf();
$options = $dompdf->getOptions();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf->setOptions($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$outDir = dirname(__DIR__, 2).'/docs';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$outFile = $outDir.'/SAS-Estoque-Mapa-UML-'.date('Y-m-d').'.pdf';
file_put_contents($outFile, $dompdf->output());

echo "PDF gerado: {$outFile}\n";
echo 'Tamanho: '.number_format(filesize($outFile) / 1024, 1)." KB\n";
