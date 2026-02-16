<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "═══════════════════════════════════════════════════════════\n";
echo "    🔧 CORRIGIR PRODUTOS COM CÓDIGO INVÁLIDO\n";
echo "═══════════════════════════════════════════════════════════\n\n";

try {
    // Busca produtos com código_barras inválido
    echo "1️⃣ Buscando produtos com código inválido...\n";
    $produtosInvalidos = DB::table('produtos')
        ->where('codigo_barras', 'Gerado automaticamente')
        ->orWhereNull('codigo_barras')
        ->orWhere('codigo_barras', '')
        ->get();
    
    echo "   📦 Produtos encontrados: " . $produtosInvalidos->count() . "\n\n";
    
    if ($produtosInvalidos->count() === 0) {
        echo "   ✅ Nenhum produto precisa de correção!\n";
        exit(0);
    }
    
    // Corrige cada produto
    echo "2️⃣ Corrigindo produtos...\n";
    $corrigidos = 0;
    
    foreach ($produtosInvalidos as $produto) {
        $novoCodigo = 'PROD-' . str_pad($produto->id, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd');
        
        echo "   🔄 ID {$produto->id}: {$produto->nome}\n";
        echo "      Código antigo: " . ($produto->codigo_barras ?: 'NULL') . "\n";
        echo "      Código novo: $novoCodigo\n";
        
        DB::table('produtos')
            ->where('id', $produto->id)
            ->update(['codigo_barras' => $novoCodigo]);
        
        $corrigidos++;
        echo "      ✅ Corrigido!\n\n";
    }
    
    echo "═══════════════════════════════════════════════════════════\n";
    echo "✅ CORREÇÃO CONCLUÍDA!\n";
    echo "   📦 Total corrigido: $corrigidos produtos\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    // Mostra produtos corrigidos
    echo "3️⃣ Produtos após correção:\n";
    $produtosAtualizados = DB::table('produtos')
        ->whereIn('id', $produtosInvalidos->pluck('id'))
        ->get();
    
    foreach ($produtosAtualizados as $produto) {
        echo "   ✅ ID {$produto->id}: {$produto->nome} | Código: {$produto->codigo_barras}\n";
    }
    
    echo "\n💡 Agora você pode cadastrar novos produtos!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . "\n";
    echo "📍 Linha: " . $e->getLine() . "\n";
    exit(1);
}
