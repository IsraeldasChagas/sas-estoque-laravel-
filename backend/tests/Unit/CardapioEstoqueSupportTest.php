<?php

namespace Tests\Unit;

use App\Support\CardapioEstoqueSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CardapioEstoqueSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchema();
    }

    protected function tearDown(): void
    {
        foreach ([
            'cardapio_estoque_movimentacoes',
            'cardapio_estoque_saldos',
            'dlv_produtos',
            'dlv_categorias',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        parent::tearDown();
    }

    private function criarSchema(): void
    {
        Schema::dropIfExists('cardapio_estoque_movimentacoes');
        Schema::dropIfExists('cardapio_estoque_saldos');
        Schema::dropIfExists('dlv_produtos');
        Schema::dropIfExists('dlv_categorias');

        Schema::create('dlv_categorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('dlv_produtos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->string('nome');
            $table->decimal('preco', 14, 2)->default(0);
            $table->integer('estoque')->default(0);
            $table->boolean('controla_estoque_cardapio')->default(true);
            $table->string('tipo_venda', 20)->default('prato');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('cardapio_estoque_saldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('dlv_produto_id');
            $table->decimal('quantidade', 14, 4)->default(0);
            $table->decimal('estoque_minimo', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['unidade_id', 'dlv_produto_id']);
        });

        Schema::create('cardapio_estoque_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('dlv_produto_id');
            $table->string('tipo', 20);
            $table->string('origem', 40);
            $table->decimal('quantidade', 14, 4);
            $table->decimal('saldo_apos', 14, 4)->default(0);
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedBigInteger('comanda_id')->nullable();
            $table->unsignedBigInteger('dlv_pedido_id')->nullable();
            $table->unsignedBigInteger('producao_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function test_entrada_saida_e_bloqueio_sem_saldo(): void
    {
        $dlvId = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 1,
            'nome' => 'Tacacá',
            'preco' => 28,
            'estoque' => 0,
            'controla_estoque_cardapio' => 1,
            'tipo_venda' => 'prato',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CardapioEstoqueSupport::entrada(1, $dlvId, 10, CardapioEstoqueSupport::ORIGEM_ABASTECIMENTO, [
            'motivo' => 'Manhã',
        ]);
        $this->assertSame(10.0, CardapioEstoqueSupport::saldo(1, $dlvId));

        CardapioEstoqueSupport::baixarVenda(1, $dlvId, 3, [
            'origem_venda' => 'balcao',
            'venda_id' => 99,
        ]);
        $this->assertSame(7.0, CardapioEstoqueSupport::saldo(1, $dlvId));

        $val = CardapioEstoqueSupport::validarSaldo(1, $dlvId, 8);
        $this->assertFalse($val['ok']);

        $this->expectException(\RuntimeException::class);
        CardapioEstoqueSupport::baixarVenda(1, $dlvId, 8, ['origem_venda' => 'balcao']);
    }

    public function test_prato_nao_baixa_admin(): void
    {
        $politica = CardapioEstoqueSupport::politicaBaixaAdmin(null);
        $this->assertTrue($politica['deve_baixar_admin']);

        $dlvId = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 1,
            'nome' => 'Prato',
            'preco' => 10,
            'tipo_venda' => 'prato',
            'controla_estoque_cardapio' => 1,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $p = CardapioEstoqueSupport::politicaBaixaAdmin($dlvId);
        $this->assertFalse($p['deve_baixar_admin']);
        $this->assertSame('prato', $p['tipo_venda']);
    }

    public function test_entrar_da_producao_por_ficha_e_produto_final(): void
    {
        Schema::table('dlv_produtos', function (Blueprint $table) {
            $table->unsignedBigInteger('ficha_tecnica_id')->nullable();
            $table->unsignedBigInteger('estoque_produto_id')->nullable();
        });

        $dlvFicha = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 1,
            'nome' => 'Tacacá cardápio',
            'preco' => 28,
            'tipo_venda' => 'prato',
            'controla_estoque_cardapio' => 1,
            'ficha_tecnica_id' => 50,
            'estoque_produto_id' => null,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dlvOutro = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 1,
            'nome' => 'Outro prato',
            'preco' => 20,
            'tipo_venda' => 'prato',
            'controla_estoque_cardapio' => 1,
            'ficha_tecnica_id' => 99,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entradas = CardapioEstoqueSupport::entrarDaProducao(1, 12, 7, 50, null, 1);
        $this->assertCount(1, $entradas);
        $this->assertSame($dlvFicha, $entradas[0]['dlv_produto_id']);
        $this->assertSame(12.0, CardapioEstoqueSupport::saldo(1, $dlvFicha));
        $this->assertSame(0.0, CardapioEstoqueSupport::saldo(1, $dlvOutro));

        $dlvSku = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => 1,
            'nome' => 'Produto final no cardápio',
            'preco' => 15,
            'tipo_venda' => 'prato',
            'controla_estoque_cardapio' => 1,
            'estoque_produto_id' => 200,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $entradas2 = CardapioEstoqueSupport::entrarDaProducao(1, 5, 8, null, 200, 1);
        $this->assertCount(1, $entradas2);
        $this->assertSame($dlvSku, $entradas2[0]['dlv_produto_id']);
        $this->assertSame(5.0, CardapioEstoqueSupport::saldo(1, $dlvSku));
    }
}
