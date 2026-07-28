<?php

namespace Tests\Feature;

use App\Support\CardapioComercialSupport;
use App\Support\PdvComercialSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PdvCardapioOperacionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();
    }

    protected function tearDown(): void
    {
        foreach (['dlv_produtos', 'dlv_categorias', 'stock_lotes', 'produtos'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    private function criarSchema(): void
    {
        Schema::dropIfExists('dlv_produtos');
        Schema::dropIfExists('dlv_categorias');
        Schema::dropIfExists('stock_lotes');
        Schema::dropIfExists('produtos');

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->decimal('preco', 14, 2)->nullable();
        });

        Schema::create('stock_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('unidade_id');
            $table->decimal('quantidade', 14, 3)->default(0);
        });

        $migration = require database_path('migrations/2026_07_17_150000_create_delivery_tables.php');
        $migration->up();

        $cardapioMigration = require database_path('migrations/2026_07_28_120000_cardapio_operacional_pdv.php');
        $cardapioMigration->up();
    }

    public function test_listar_produtos_pdv_usa_cardapio_da_unidade(): void
    {
        DB::table('produtos')->insert(['id' => 10, 'nome' => 'Estoque Tacacá', 'ativo' => 1, 'preco' => 5]);
        DB::table('stock_lotes')->insert([
            'produto_id' => 10,
            'unidade_id' => 1,
            'quantidade' => 12,
        ]);
        $catId = DB::table('dlv_categorias')->insertGetId([
            'unidade_id' => 1,
            'nome' => 'Pratos',
            'ordem' => 1,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('dlv_produtos')->insert([
            'unidade_id' => 1,
            'categoria_id' => $catId,
            'estoque_produto_id' => 10,
            'nome' => 'Tacacá delivery',
            'preco' => 28.5,
            'ativo' => 1,
            'visivel_loja' => 1,
            'visivel_pdv' => 1,
            'ordem' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lista = PdvComercialSupport::listarProdutosPdv(1);

        $this->assertCount(1, $lista);
        $this->assertSame('cardapio', $lista[0]['fonte']);
        $this->assertSame('Tacacá delivery', $lista[0]['nome']);
        $this->assertSame(28.5, $lista[0]['preco']);
        $this->assertSame(10, $lista[0]['estoque_produto_id']);
        $this->assertTrue($lista[0]['disponivel']);
    }

    public function test_resolver_linha_venda_por_cardapio_produto_id(): void
    {
        DB::table('produtos')->insert(['id' => 11, 'nome' => 'Produto base', 'ativo' => 1, 'preco' => 1]);
        $dlvId = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 2,
            'estoque_produto_id' => 11,
            'nome' => 'Item cardápio',
            'preco' => 19.9,
            'ativo' => 1,
            'visivel_loja' => 1,
            'visivel_pdv' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = CardapioComercialSupport::resolverLinhaVenda(2, [
            'cardapio_produto_id' => $dlvId,
            'quantidade' => 1,
        ]);

        $this->assertSame(11, $res['produto_id']);
        $this->assertSame($dlvId, $res['cardapio_produto_id']);
        $this->assertSame(19.9, $res['preco_unitario']);
        $this->assertSame('Item cardápio', $res['nome']);
    }

    public function test_item_oculto_pdv_nao_entra_na_lista(): void
    {
        DB::table('produtos')->insert(['id' => 12, 'nome' => 'X', 'ativo' => 1, 'preco' => 1]);
        DB::table('dlv_produtos')->insert([
            'unidade_id' => 3,
            'estoque_produto_id' => 12,
            'nome' => 'Só delivery',
            'preco' => 10,
            'ativo' => 1,
            'visivel_loja' => 1,
            'visivel_pdv' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lista = PdvComercialSupport::listarProdutosPdv(3);

        $this->assertSame([], $lista);
    }

    public function test_produto_multi_unidade_aparece_somente_onde_marcado(): void
    {
        $migration = require database_path('migrations/2026_07_28_140000_dlv_produto_unidades.php');
        $migration->up();

        DB::table('produtos')->insert([
            ['id' => 20, 'nome' => 'P20', 'ativo' => 1, 'preco' => 1],
            ['id' => 21, 'nome' => 'P21', 'ativo' => 1, 'preco' => 1],
        ]);
        DB::table('stock_lotes')->insert([
            ['produto_id' => 20, 'unidade_id' => 1, 'quantidade' => 5],
            ['produto_id' => 21, 'unidade_id' => 2, 'quantidade' => 5],
        ]);
        $dlvId = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 1,
            'estoque_produto_id' => 20,
            'nome' => 'Compartilhado',
            'preco' => 15,
            'ativo' => 1,
            'visivel_loja' => 1,
            'visivel_pdv' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('dlv_produto_unidades')->insert([
            ['produto_id' => $dlvId, 'unidade_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['produto_id' => $dlvId, 'unidade_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertCount(1, PdvComercialSupport::listarProdutosPdv(1));
        $this->assertCount(1, PdvComercialSupport::listarProdutosPdv(2));
        $nomesUnidade3 = array_column(PdvComercialSupport::listarProdutosPdv(3), 'nome');
        $this->assertNotContains('Compartilhado', $nomesUnidade3);
    }
}
