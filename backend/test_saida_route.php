<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function callSaida($app, array $payload): void {
    $request = Illuminate\Http\Request::create('/api/saida', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode($payload));
    $response = $app->handle($request);
    echo "Payload: " . json_encode($payload) . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: " . $response->getContent() . "\n\n";
}

$row = DB::table('stock_lotes as s')
    ->join('produtos as p', 'p.id', '=', 's.produto_id')
    ->join('unidades as u', 'u.id', '=', 's.unidade_id')
    ->where('s.quantidade', '>', 0)
    ->select('s.produto_id', 's.unidade_id', 's.quantidade', 'p.nome', 'p.unidade_base', 'u.nome as unidade_nome')
    ->orderByDesc('s.quantidade')
    ->first();

if (!$row) { echo "Sem estoque\n"; exit(1); }
$usuario = DB::table('usuarios')->where('ativo', 1)->first();

echo "Teste com: {$row->nome} base={$row->unidade_base} estoque={$row->quantidade} unidade={$row->unidade_nome}\n\n";

$base = [
    'produto_id' => (int) $row->produto_id,
    'de_unidade_id' => (int) $row->unidade_id,
    'qtd' => 0.001,
    'usuario_id' => (int) $usuario->id,
    'forcar' => false,
];

callSaida($app, array_merge($base, ['motivo' => 'CONSUMO']));
callSaida($app, array_merge($base, ['motivo' => 'PRODUCAO', 'unidade_informada' => 'UND']));
callSaida($app, array_merge($base, ['motivo' => 'PRODUCAO', 'unidade_informada' => 'KG']));
