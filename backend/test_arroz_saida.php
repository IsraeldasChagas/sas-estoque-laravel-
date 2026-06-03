<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$produto = DB::table('produtos')->where('nome', 'like', '%ARROZ-SOLTINHO%')->first();
foreach ([26, 29] as $uid) {
    $est = DB::table('stock_lotes')->where('produto_id', $produto->id)->where('unidade_id', $uid)->sum('quantidade');
    $nome = DB::table('unidades')->where('id', $uid)->value('nome');
    echo "Unidade {$uid} ({$nome}): estoque={$est}\n";
}

$usuario = DB::table('usuarios')->where('id', 6)->first() ?? DB::table('usuarios')->where('ativo',1)->first();

$payload = [
    'produto_id' => (int) $produto->id,
    'de_unidade_id' => 26,
    'qtd' => 1.5,
    'motivo' => 'PRODUCAO',
    'usuario_id' => (int) $usuario->id,
    'forcar' => false,
    'unidade_informada' => 'UND',
];

$request = Illuminate\Http\Request::create('/api/saida', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
], json_encode($payload));
$response = $app->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo $response->getContent() . "\n";
