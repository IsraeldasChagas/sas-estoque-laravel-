<?php
declare(strict_types=1);

// Mock API local para testar visualização de PDF no frontend.
// Rode com: php -S 127.0.0.1:8011 -t mock-api mock-api/index.php

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function buildSimplePdf(string $text): string {
    // Gera um PDF mínimo com offsets corretos (xref).
    $objects = [];

    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

    // Conteúdo (stream) com texto simples usando Helvetica.
    $content = "BT\n/F1 24 Tf\n50 120 Td\n(" . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ") Tj\nET\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200]\n   /Resources << /Font << /F1 5 0 R >> >>\n   /Contents 4 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0]; // xref entry 0
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($offsets)) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i < count($offsets); $i++) {
        $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size " . count($offsets) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";
    return $pdf;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
    http_response_code(204);
    exit;
}

if ($method !== 'GET') {
    sendJson(['error' => 'Method not allowed'], 405);
}

if ($uri === '/api/rh/candidatos/2/curriculo') {
    $pdf = buildSimplePdf('Curriculo PDF OK (mock)');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="curriculo-2.pdf"');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Usuario-Id, X-Device-Model, X-Device-Platform');
    header('Access-Control-Expose-Headers: Content-Disposition, Content-Type, Content-Length');
    echo $pdf;
    exit;
}

if ($uri === '/api/rh/candidatos/2') {
    sendJson([
        'candidato' => ['id' => 2, 'nome' => 'Mock', 'status' => 'novo', 'consentimento_lgpd' => true],
        'tem_curriculo' => true,
    ]);
}

sendJson(['error' => 'Not found', 'path' => $uri], 404);

