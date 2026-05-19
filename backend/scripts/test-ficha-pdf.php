<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 1);
$row = \Illuminate\Support\Facades\DB::table('fichas_tecnicas')->where('id', $id)->first();
if (!$row) {
    fwrite(STDERR, "Ficha $id não encontrada\n");
    exit(1);
}

$h = static fn ($s) => htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
$modoRaw = (string) ($row->modo_preparo ?? '');
$modoHtml = '—';
if (trim($modoRaw) !== '') {
    $modoTexto = html_entity_decode(strip_tags($modoRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $modoTexto = preg_replace("/\r\n|\r/", "\n", $modoTexto);
    $modoTexto = trim($modoTexto);
    if ($modoTexto !== '') {
        $modoHtml = '<p>' . $h($modoTexto) . '</p>';
    }
}

$html = '<!DOCTYPE html><html><body><h1>' . $h($row->nome_prato) . '</h1>' . $modoHtml . '</body></html>';

try {
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $out = $dompdf->output();
    echo 'OK bytes=' . strlen($out) . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERR: ' . $e->getMessage() . "\n");
    exit(2);
}
