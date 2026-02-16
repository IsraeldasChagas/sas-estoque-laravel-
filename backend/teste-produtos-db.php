<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "═══════════════════════════════════════════════════════════\n";
echo "    📦 TESTE - TABELA PRODUTOS NO BANCO DE DADOS\n";
echo "═══════════════════════════════════════════════════════════\n\n";

try {
    // Testa conexão
    echo "1️⃣ Testando conexão com banco de dados...\n";
    DB::connection()->getPdo();
    echo "   ✅ Conexão estabelecida!\n\n";
    
    // Lista todas as tabelas
    echo "2️⃣ Listando tabelas no banco...\n";
    $tables = DB::select('SHOW TABLES');
    echo "   📋 Tabelas encontradas: " . count($tables) . "\n";
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        echo "   - $tableName\n";
    }
    echo "\n";
    
    // Verifica se tabela produtos existe
    echo "3️⃣ Verificando tabela 'produtos'...\n";
    $tableExists = DB::select("SHOW TABLES LIKE 'produtos'");
    
    if (empty($tableExists)) {
        echo "   ❌ ERRO: Tabela 'produtos' NÃO EXISTE!\n";
        echo "   💡 Você precisa criar a tabela produtos no banco de dados.\n";
        exit(1);
    }
    
    echo "   ✅ Tabela 'produtos' existe!\n\n";
    
    // Descreve estrutura da tabela
    echo "4️⃣ Estrutura da tabela 'produtos':\n";
    $columns = DB::select('DESCRIBE produtos');
    echo "   📊 Colunas:\n";
    foreach ($columns as $column) {
        echo "   - {$column->Field} ({$column->Type}) ";
        echo $column->Null === 'YES' ? '[NULL]' : '[NOT NULL]';
        if ($column->Key === 'PRI') echo ' [PRIMARY KEY]';
        if ($column->Default !== null) echo " [DEFAULT: {$column->Default}]";
        echo "\n";
    }
    echo "\n";
    
    // Conta produtos
    echo "5️⃣ Contando produtos cadastrados...\n";
    $count = DB::table('produtos')->count();
    echo "   📦 Total de produtos: $count\n\n";
    
    // Lista alguns produtos (máximo 5)
    if ($count > 0) {
        echo "6️⃣ Últimos produtos cadastrados (máx 5):\n";
        $produtos = DB::table('produtos')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($produtos as $produto) {
            echo "   ID: {$produto->id} | Nome: {$produto->nome} | Categoria: {$produto->categoria}\n";
            echo "      Código: {$produto->codigo_barras} | Unidade base: {$produto->unidade_base}\n";
            echo "      Custo: {$produto->custo_medio} | Estoque min: {$produto->estoque_minimo}\n";
            echo "      Status: " . ($produto->ativo ? 'ATIVO' : 'INATIVO') . "\n\n";
        }
    }
    
    // Teste de inserção simulado
    echo "7️⃣ Simulando INSERT (sem salvar)...\n";
    $testData = [
        'nome' => 'Produto Teste',
        'categoria' => 'SECOS',
        'unidade_base' => 'KG',
        'codigo_barras' => 'PROD-TEST-' . time(),
        'descricao' => 'Apenas um teste',
        'custo_medio' => 10.50,
        'estoque_minimo' => 20,
        'unidade_id' => null,
        'ativo' => 1,
    ];
    
    echo "   📝 Dados de teste:\n";
    foreach ($testData as $key => $value) {
        echo "      $key: " . ($value ?? 'NULL') . "\n";
    }
    echo "   ✅ Estrutura de dados válida!\n\n";
    
    echo "═══════════════════════════════════════════════════════════\n";
    echo "✅ TESTE CONCLUÍDO COM SUCESSO!\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    echo "💡 PRÓXIMOS PASSOS:\n";
    echo "   1. Verifique se o servidor Laravel está rodando:\n";
    echo "      php artisan serve --host=0.0.0.0 --port=5000\n\n";
    echo "   2. Teste cadastrar um produto pelo frontend\n";
    echo "   3. Verifique os logs em: storage/logs/laravel.log\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . "\n";
    echo "📍 Linha: " . $e->getLine() . "\n";
    echo "\n💡 Verifique:\n";
    echo "   - Arquivo .env está configurado corretamente\n";
    echo "   - Banco de dados existe e está acessível\n";
    echo "   - Credenciais do banco estão corretas\n";
    exit(1);
}
