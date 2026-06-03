<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simula POST /saida mínimo — só valida insert de movimentação (sem alterar estoque)
$produto = DB::table('produtos')->where('ativo', 1)->first();
$unidade = DB::table('unidades')->where('ativo', 1)->first();
$usuario = DB::table('usuarios')->where('ativo', 1)->first();

if (!$produto || !$unidade || !$usuario) {
    echo "Dados base ausentes\n";
    exit(1);
}

echo "Produto: {$produto->id} {$produto->nome} base={$produto->unidade_base}\n";

$testUnits = ['UN', 'UND', 'KG', 'G', 'ML', 'L', 'KL'];
foreach ($testUnits as $u) {
    try {
        DB::beginTransaction();
        $id = DB::table('movimentacoes')->insertGetId([
            'produto_id' => $produto->id,
            'lote_id' => null,
            'usuario_id' => $usuario->id,
            'tipo' => 'SAIDA',
            'qtd' => 0.001,
            'unidade' => $u,
            'custo_unitario' => 0,
            'data_mov' => now(),
            'motivo' => 'PRODUCAO',
            'observacao' => 'TESTE_AUTO_DELETE',
            'de_unidade_id' => $unidade->id,
            'para_unidade_id' => null,
        ]);
        DB::table('movimentacoes')->where('id', $id)->delete();
        DB::commit();
        echo "OK unidade={$u}\n";
    } catch (Throwable $e) {
        DB::rollBack();
        echo "FAIL unidade={$u}: " . $e->getMessage() . "\n";
    }
}
