<?php
// Teste simples da API de boletos

$url = 'http://localhost:5000/api/boletos';

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\n" .
                    "X-Usuario-Id: 1\r\n",
        'method' => 'GET',
    ]
];

$context = stream_context_create($options);
$result = @file_get_contents($url, false, $context);

if ($result === false) {
    echo "ERRO: Não foi possível conectar à API\n";
    echo "URL: $url\n";
    if (isset($http_response_header)) {
        echo "Headers de resposta:\n";
        print_r($http_response_header);
    }
    exit(1);
}

$boletos = json_decode($result, true);

if ($boletos === null) {
    echo "ERRO: Resposta não é JSON válido\n";
    echo "Resposta:\n$result\n";
    exit(1);
}

echo "✅ SUCESSO! API funcionando!\n";
echo "Total de boletos: " . count($boletos) . "\n\n";

if (count($boletos) > 0) {
    echo "Primeiros 3 boletos:\n";
    foreach (array_slice($boletos, 0, 3) as $i => $boleto) {
        echo "\n#" . ($i + 1) . ":\n";
        echo "  ID: " . $boleto['id'] . "\n";
        echo "  Fornecedor: " . $boleto['fornecedor'] . "\n";
        echo "  Valor: R$ " . number_format($boleto['valor'], 2, ',', '.') . "\n";
        echo "  Status: " . $boleto['status'] . "\n";
    }
}
