<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaidaEmbalagemTest extends TestCase
{
    private const TABELAS = ['movimentacoes', 'stock_lotes', 'lotes', 'locais', 'produtos', 'unidades', 'usuarios'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::TABELAS as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('perfil');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('unidades', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
        });
        Schema::create('produtos', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('unidade_base', 10)->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('locais', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->unsignedBigInteger('unidade_id');
            $t->string('tipo')->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('lotes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('produto_id');
            $t->unsignedBigInteger('unidade_id');
            $t->string('numero_lote');
            $t->decimal('qtd_atual', 14, 4)->default(0);
            $t->date('data_validade')->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('stock_lotes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('produto_id');
            $t->unsignedBigInteger('unidade_id');
            $t->string('codigo_lote');
            $t->decimal('quantidade', 14, 4)->default(0);
            $t->decimal('custo_unitario', 14, 4)->default(0);
        });
        Schema::create('movimentacoes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('produto_id');
            $t->unsignedBigInteger('lote_id')->nullable();
            $t->unsignedBigInteger('usuario_id')->nullable();
            $t->string('tipo', 24)->nullable();
            $t->decimal('qtd', 14, 4)->default(0);
            $t->string('unidade', 10)->nullable();
            $t->decimal('custo_unitario', 14, 4)->nullable();
            $t->dateTime('data_mov')->nullable();
            $t->string('motivo')->nullable();
            $t->text('observacao')->nullable();
            $t->unsignedBigInteger('de_unidade_id')->nullable();
            $t->unsignedBigInteger('para_unidade_id')->nullable();
        });

        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN']);
        DB::table('unidades')->insert(['id' => 1, 'nome' => 'Loja Centro']);
        DB::table('produtos')->insert([
            ['id' => 10, 'nome' => 'Refrigerante lata', 'unidade_base' => 'UND'],
            ['id' => 11, 'nome' => 'Farinha', 'unidade_base' => 'KG'],
        ]);
        DB::table('lotes')->insert([
            ['produto_id' => 10, 'unidade_id' => 1, 'numero_lote' => 'L-REF', 'qtd_atual' => 30],
            ['produto_id' => 11, 'unidade_id' => 1, 'numero_lote' => 'L-FAR', 'qtd_atual' => 5],
        ]);
        DB::table('stock_lotes')->insert([
            ['produto_id' => 10, 'unidade_id' => 1, 'codigo_lote' => 'L-REF', 'quantidade' => 30, 'custo_unitario' => 3],
            ['produto_id' => 11, 'unidade_id' => 1, 'codigo_lote' => 'L-FAR', 'quantidade' => 5, 'custo_unitario' => 8],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABELAS as $t) {
            Schema::dropIfExists($t);
        }
        parent::tearDown();
    }

    private function saida(array $dados)
    {
        return $this->postJson('/api/saida', $dados + ['de_unidade_id' => 1, 'usuario_id' => 1], ['X-Usuario-Id' => '1']);
    }

    private function saldo(int $produtoId): float
    {
        return (float) DB::table('stock_lotes')->where('produto_id', $produtoId)->sum('quantidade');
    }

    public function test_saida_em_caixa_baixa_quantidade_por_embalagem(): void
    {
        $this->saida(['produto_id' => 10, 'motivo' => 'CONSUMO', 'qtd' => 2, 'unidade_informada' => 'CAIXA'])
            ->assertStatus(422);
        $this->assertEqualsWithDelta(30, $this->saldo(10), 0.0001);

        $this->saida(['produto_id' => 10, 'motivo' => 'CONSUMO', 'qtd' => 2, 'unidade_informada' => 'CAIXA', 'fator_embalagem' => 12])
            ->assertSuccessful();
        $this->assertEqualsWithDelta(6, $this->saldo(10), 0.0001);
        $mov = DB::table('movimentacoes')->where('produto_id', 10)->first();
        $this->assertEqualsWithDelta(24, (float) $mov->qtd, 0.0001);
        $this->assertStringContainsString('2 caixa(s) de 12 UND = 24 UND', $mov->observacao);
        $this->assertStringContainsString('Lotes:', $mov->observacao);
    }

    public function test_saida_em_gramas_de_produto_em_quilo_converte_em_qualquer_motivo(): void
    {
        $this->saida(['produto_id' => 11, 'motivo' => 'PERDA', 'qtd' => 500, 'unidade_informada' => 'G'])->assertSuccessful();
        $this->assertEqualsWithDelta(4.5, $this->saldo(11), 0.0001);

        $this->saida(['produto_id' => 11, 'motivo' => 'PERDA', 'qtd' => 1])->assertSuccessful();
        $this->assertEqualsWithDelta(3.5, $this->saldo(11), 0.0001);
    }
}
