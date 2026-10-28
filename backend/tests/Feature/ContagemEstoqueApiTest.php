<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContagemEstoqueApiTest extends TestCase
{
    private const TABELAS = [
        'contagem_configuracoes', 'contagem_auditoria', 'contagem_decisoes', 'contagem_lancamentos',
        'contagem_itens', 'contagem_inventarios', 'movimentacoes', 'stock_lotes', 'lotes', 'locais',
        'produtos', 'unidades', 'usuarios',
    ];

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
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->text('permissoes_menu')->nullable();
        });
        Schema::create('unidades', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('produtos', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('codigo_barras')->nullable();
            $t->string('categoria')->nullable();
            $t->string('unidade_base', 10)->nullable();
            $t->unsignedBigInteger('unidade_id')->nullable();
            $t->boolean('ativo')->default(true);
            $t->decimal('custo_medio', 14, 4)->nullable();
            $t->string('foto')->nullable();
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
            $t->string('unidade', 10)->nullable();
            $t->decimal('custo_unitario', 14, 4)->nullable();
            $t->date('data_fabricacao')->nullable();
            $t->date('data_validade')->nullable();
            $t->unsignedBigInteger('local_id')->nullable();
            $t->boolean('ativo')->default(true);
            $t->dateTime('criado_em')->nullable();
        });
        Schema::create('stock_lotes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('produto_id');
            $t->unsignedBigInteger('unidade_id');
            $t->string('codigo_lote')->unique();
            $t->decimal('quantidade', 14, 4)->default(0);
            $t->decimal('custo_unitario', 14, 4)->default(0);
            $t->date('data_fabricacao')->nullable();
            $t->date('data_validade')->nullable();
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
            $t->string('origem', 50)->nullable();
            $t->unsignedBigInteger('de_unidade_id')->nullable();
            $t->unsignedBigInteger('para_unidade_id')->nullable();
        });

        $mig = require database_path('migrations/2026_10_28_100000_create_contagem_estoque_tables.php');
        $mig->up();

        DB::table('unidades')->insert([['id' => 1, 'nome' => 'Loja Centro'], ['id' => 2, 'nome' => 'Loja Norte']]);
        DB::table('usuarios')->insert([
            ['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1, 'unidade_id' => null],
            ['id' => 2, 'nome' => 'Estoquista', 'perfil' => 'ESTOQUISTA', 'ativo' => 1, 'unidade_id' => 1],
        ]);
        DB::table('produtos')->insert([
            ['id' => 10, 'nome' => 'Arroz', 'categoria' => 'CEREAIS', 'unidade_base' => 'UND', 'unidade_id' => 1, 'custo_medio' => 2],
            ['id' => 11, 'nome' => 'Picanha', 'categoria' => 'CARNES', 'unidade_base' => 'KG', 'unidade_id' => 1, 'custo_medio' => 10],
            ['id' => 12, 'nome' => 'Sal', 'categoria' => 'CEREAIS', 'unidade_base' => 'UND', 'unidade_id' => 1, 'custo_medio' => 1],
            ['id' => 13, 'nome' => 'De outra loja', 'categoria' => 'CEREAIS', 'unidade_base' => 'UND', 'unidade_id' => 2, 'custo_medio' => 1],
        ]);
        DB::table('stock_lotes')->insert([
            ['produto_id' => 10, 'unidade_id' => 1, 'codigo_lote' => 'L-ARROZ', 'quantidade' => 10, 'custo_unitario' => 2],
            ['produto_id' => 11, 'unidade_id' => 1, 'codigo_lote' => 'L-PIC', 'quantidade' => 5, 'custo_unitario' => 10],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABELAS as $t) {
            Schema::dropIfExists($t);
        }
        parent::tearDown();
    }

    private function api(string $method, string $uri, int $usuarioId, array $body = [])
    {
        return $this->json($method, '/api/contagem' . $uri, $body, ['X-Usuario-Id' => (string) $usuarioId]);
    }

    private function saldo(int $produtoId): float
    {
        return (float) DB::table('stock_lotes')->where('produto_id', $produtoId)->where('unidade_id', 1)->sum('quantidade');
    }

    public function test_fluxo_completo_de_contagem_com_ajuste_no_estoque(): void
    {
        $this->api('GET', '/meta', 1)->assertOk()->assertJsonPath('capacidades.aprovar', true);
        $this->api('GET', '/meta', 2)->assertOk()
            ->assertJsonPath('capacidades.contar', true)
            ->assertJsonPath('capacidades.aprovar', false)
            ->assertJsonCount(1, 'unidades');

        $rascunho = $this->api('POST', '/inventarios', 1, [
            'unidade_id' => 1, 'tipo' => 'completa', 'cega' => true, 'equipe_ids' => [2],
        ])->assertOk()->assertJsonPath('inventario.numero', 'INV-0001')->assertJsonPath('inventario.status', 'rascunho');
        $id = $rascunho->json('inventario.id');

        $this->api('POST', "/inventarios/{$id}/iniciar", 1)->assertOk()
            ->assertJsonPath('inventario.status', 'em_contagem')
            ->assertJsonPath('inventario.total_itens', 3);

        $outro = $this->api('POST', '/inventarios', 1, ['unidade_id' => 1, 'tipo' => 'completa'])->json('inventario.id');
        $this->api('POST', "/inventarios/{$outro}/iniciar", 1)->assertStatus(422);
        $this->api('DELETE', "/inventarios/{$outro}", 1)->assertOk();

        $cega = $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 10, 'quantidade' => '8'])->assertOk();
        $arroz = collect($cega->json('inventario.itens'))->firstWhere('produto_id', 10);
        $this->assertNull($arroz['diferenca']);
        $this->assertNull($arroz['sistema']);
        $this->assertSame(8.0, (float) $arroz['minha_quantidade']);

        $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 12, 'quantidade' => '1,5'])->assertStatus(422);
        $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 12, 'quantidade' => ''])->assertStatus(422);
        $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 12, 'quantidade' => '0'])->assertOk();
        $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 11, 'quantidade' => '5,5'])->assertOk();

        DB::table('stock_lotes')->where('codigo_lote', 'L-ARROZ')->update(['quantidade' => 9]);

        $fim = $this->api('POST', "/inventarios/{$id}/finalizar", 2)->assertOk();
        $status = collect($fim->json('inventario.itens'))->pluck('status', 'produto_id');
        $this->assertSame('falta', $status[10]);
        $this->assertSame('sobra', $status[11]);
        $this->assertSame('sem_divergencia', $status[12]);

        $this->api('POST', "/inventarios/{$id}/decidir", 2, ['produto_id' => 10, 'decisao' => 'aprovado', 'justificativa' => 'ok'])->assertStatus(422);
        $this->api('POST', "/inventarios/{$id}/decidir", 1, ['produto_id' => 10, 'decisao' => 'aprovado', 'justificativa' => ''])->assertStatus(422);

        $this->api('POST', "/inventarios/{$id}/decidir", 1, ['produto_id' => 10, 'decisao' => 'recontagem', 'justificativa' => 'Conferir prateleira'])->assertOk();
        $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 10, 'quantidade' => '8'])->assertStatus(422);
        $this->api('POST', "/inventarios/{$id}/lancar", 2, ['produto_id' => 10, 'quantidade' => '8', 'justificativa' => 'Recontado'])->assertOk();

        $this->api('POST', "/inventarios/{$id}/encerrar", 1)->assertStatus(422);

        $this->api('POST', "/inventarios/{$id}/decidir", 1, ['produto_id' => 10, 'decisao' => 'aprovado', 'justificativa' => 'Quebra'])->assertOk();
        $this->assertEqualsWithDelta(8.0, $this->saldo(10), 0.0001);

        $this->api('POST', "/inventarios/{$id}/decidir", 1, ['produto_id' => 11, 'decisao' => 'aprovado', 'justificativa' => 'Pesagem'])->assertOk();
        $this->assertEqualsWithDelta(5.5, $this->saldo(11), 0.0001);
        $this->api('POST', "/inventarios/{$id}/decidir", 1, ['produto_id' => 11, 'decisao' => 'aprovado', 'justificativa' => 'De novo'])->assertStatus(422);
        $this->assertEqualsWithDelta(5.5, $this->saldo(11), 0.0001);

        $movs = DB::table('movimentacoes')->where('motivo', 'AJUSTE_INVENTARIO')->orderBy('id')->get();
        $this->assertCount(2, $movs);
        $this->assertSame('SAIDA', $movs[0]->tipo);
        $this->assertEqualsWithDelta(1.0, (float) $movs[0]->qtd, 0.0001);
        $this->assertSame('ENTRADA', $movs[1]->tipo);
        $this->assertEqualsWithDelta(0.5, (float) $movs[1]->qtd, 0.0001);

        $this->api('POST', "/inventarios/{$id}/encerrar", 1)->assertOk();
        $final = $this->api('GET', "/inventarios/{$id}", 1)->assertOk()->assertJsonPath('status', 'encerrado');
        $arrozFinal = collect($final->json('itens'))->firstWhere('produto_id', 10);
        $this->assertCount(2, $arrozFinal['lancamentos']);
        $this->assertSame('aprovado', $arrozFinal['status']);

        $this->api('POST', "/inventarios/{$id}/lancar", 1, ['produto_id' => 12, 'quantidade' => '3'])->assertStatus(422);
    }

    public function test_contagem_parcial_e_rotativa_respeitam_o_escopo(): void
    {
        $parcial = $this->api('POST', '/inventarios', 1, ['unidade_id' => 1, 'tipo' => 'parcial', 'produto_ids' => [11]])->json('inventario.id');
        $this->api('POST', "/inventarios/{$parcial}/iniciar", 1)->assertOk()->assertJsonPath('inventario.total_itens', 1);

        $rotativa = $this->api('POST', '/inventarios', 1, ['unidade_id' => 2, 'tipo' => 'rotativa', 'categoria' => 'cereais'])->json('inventario.id');
        $this->api('POST', "/inventarios/{$rotativa}/iniciar", 1)->assertOk()
            ->assertJsonPath('inventario.total_itens', 1)
            ->assertJsonPath('inventario.itens.0.produto_id', 13);

        $this->api('POST', '/inventarios', 2, ['unidade_id' => 2, 'tipo' => 'completa'])->assertStatus(422);
        $this->api('POST', '/inventarios', 1, ['unidade_id' => 1, 'tipo' => 'parcial'])->assertStatus(422);
    }

    public function test_configuracao_so_para_quem_tem_acesso(): void
    {
        $this->api('PUT', '/config', 2, ['cega_padrao' => true])->assertStatus(422);
        $this->api('PUT', '/config', 1, ['cega_padrao' => true])->assertOk()->assertJsonPath('config.cega_padrao', true);
        $this->api('GET', '/meta', 2)->assertJsonPath('config.cega_padrao', true);
    }
}
