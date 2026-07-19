<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FidelidadeApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas', 'usuarios',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->json('permissoes_menu')->nullable();
        });

        DB::table('usuarios')->insert([
            [
                'id' => 1,
                'nome' => 'Administrador',
                'perfil' => 'ADMIN',
                'ativo' => 1,
                'unidade_id' => 1,
                'permissoes_menu' => null,
            ],
            [
                'id' => 2,
                'nome' => 'Gerente Unidade 2',
                'perfil' => 'GERENTE',
                'ativo' => 1,
                'unidade_id' => 2,
                'permissoes_menu' => null,
            ],
            [
                'id' => 3,
                'nome' => 'Atendente sem permissão',
                'perfil' => 'ATENDENTE',
                'ativo' => 1,
                'unidade_id' => 1,
                'permissoes_menu' => json_encode([]),
            ],
            [
                'id' => 4,
                'nome' => 'Caixa com cartões',
                'perfil' => 'CAIXA',
                'ativo' => 1,
                'unidade_id' => 1,
                'permissoes_menu' => json_encode(['fidelidadeCartoes', 'fidelidadeHistorico']),
            ],
        ]);

        $migration = require database_path('migrations/2026_07_17_140000_create_fidelidade_tables.php');
        $migration->up();

        $pctMigration = require database_path('migrations/2026_07_18_220000_add_desconto_percentual_to_fid_programas.php');
        $pctMigration->up();

        $catalogoMigration = require database_path('migrations/2026_07_19_180000_add_catalogo_consulta_to_fid_programas.php');
        $catalogoMigration->up();

        $this->ensureFidelidadeRoutes();
    }

    protected function tearDown(): void
    {
        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas', 'usuarios',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    private function ensureFidelidadeRoutes(): void
    {
        $exists = collect(Route::getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), 'fidelidade/programa')
        );
        if (! $exists) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/fidelidade_routes.php'));
        }
    }

    private function headers(int $usuarioId = 1): array
    {
        return ['X-Usuario-Id' => (string) $usuarioId];
    }

    public function test_programa_upsert(): void
    {
        $this->withHeaders($this->headers())->putJson('/api/fidelidade/programa', [
            'unidade_id' => 1,
            'ativo' => true,
            'nome_exibicao' => 'Cartão do lanche',
            'modo' => 'selos',
            'pedidos_meta' => 5,
            'pontos_por_selo' => 2,
            'tipo_recompensa_padrao' => 'desconto_valor',
            'valor_desconto' => 15.5,
            'texto_recompensa' => 'Desconto no combo',
        ])->assertOk()
            ->assertJsonPath('programa.nome_exibicao', 'Cartão do lanche')
            ->assertJsonPath('programa.pedidos_meta', 5)
            ->assertJsonPath('programa.ativo', 1);

        $this->withHeaders($this->headers())->putJson('/api/fidelidade/programa', [
            'unidade_id' => 1,
            'pedidos_meta' => 8,
        ])->assertOk()
            ->assertJsonPath('programa.pedidos_meta', 8)
            ->assertJsonPath('programa.nome_exibicao', 'Cartão do lanche');

        $this->assertDatabaseCount('fid_programas', 1);
    }

    public function test_programa_catalogo_consulta_permite_mais_produtos_que_qtd_escolha(): void
    {
        Schema::dropIfExists('dlv_produtos');
        Schema::create('dlv_produtos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome');
            $table->decimal('preco', 14, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('visivel_loja')->default(true);
        });

        DB::table('dlv_produtos')->insert([
            ['id' => 601, 'unidade_id' => 1, 'nome' => 'Produto 1', 'preco' => 10.00, 'ativo' => 1, 'visivel_loja' => 1],
            ['id' => 602, 'unidade_id' => 1, 'nome' => 'Produto 2', 'preco' => 12.00, 'ativo' => 1, 'visivel_loja' => 1],
            ['id' => 603, 'unidade_id' => 1, 'nome' => 'Produto 3', 'preco' => 14.00, 'ativo' => 1, 'visivel_loja' => 1],
        ]);

        $this->withHeaders($this->headers())->putJson('/api/fidelidade/programa', [
            'unidade_id' => 1,
            'tipo_recompensa_padrao' => 'catalogo_consulta',
            'catalogo_qtd_escolhas' => 1,
            'catalogo_produtos_ids' => [601, 602, 603],
            'ativo' => true,
        ])->assertOk()
            ->assertJsonPath('programa.catalogo_qtd_escolhas', 1)
            ->assertJsonCount(3, 'programa.catalogo_produtos');
    }

    public function test_programa_catalogo_consulta_salva_produtos_e_quantidade(): void
    {
        Schema::dropIfExists('dlv_produtos');
        Schema::create('dlv_produtos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome');
            $table->decimal('preco', 14, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('visivel_loja')->default(true);
        });

        DB::table('dlv_produtos')->insert([
            ['id' => 501, 'unidade_id' => 1, 'nome' => 'Tacacá', 'preco' => 18.00, 'ativo' => 1, 'visivel_loja' => 1],
            ['id' => 502, 'unidade_id' => 1, 'nome' => 'Açaí 500ml', 'preco' => 22.00, 'ativo' => 1, 'visivel_loja' => 1],
        ]);

        $this->withHeaders($this->headers())->putJson('/api/fidelidade/programa', [
            'unidade_id' => 1,
            'nome_exibicao' => 'Cartão consulta',
            'pedidos_meta' => 10,
            'tipo_recompensa_padrao' => 'catalogo_consulta',
            'catalogo_qtd_escolhas' => 2,
            'catalogo_produtos_ids' => [501, 502],
            'ativo' => true,
        ])->assertOk()
            ->assertJsonPath('programa.tipo_recompensa_padrao', 'catalogo_consulta')
            ->assertJsonPath('programa.catalogo_qtd_escolhas', 2)
            ->assertJsonCount(2, 'programa.catalogo_produtos');

        $this->withHeaders($this->headers())->getJson('/api/fidelidade/catalogo-consulta/produtos?unidade_id=1')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('delivery_disponivel', true);

        $nomes = collect($this->withHeaders($this->headers())->getJson('/api/fidelidade/catalogo-consulta/produtos?unidade_id=1')->json('items'))
            ->pluck('nome')
            ->all();
        $this->assertContains('Tacacá', $nomes);
        $this->assertContains('Açaí 500ml', $nomes);
    }

    public function test_catalogo_consulta_usa_loja_vinculada_por_unidade_fidelidade(): void
    {
        Schema::dropIfExists('dlv_loja_config');
        Schema::dropIfExists('dlv_produtos');
        Schema::create('dlv_loja_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('unidade_fidelidade_id')->nullable();
            $table->string('slug')->unique();
            $table->string('nome_loja')->nullable();
            $table->boolean('ativo')->default(1);
        });
        Schema::create('dlv_produtos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome');
            $table->decimal('preco', 14, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('visivel_loja')->default(true);
            $table->unsignedInteger('ordem')->default(0);
        });

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => 99,
            'unidade_fidelidade_id' => 1,
            'slug' => 'loja-delivery-teste',
            'nome_loja' => 'Loja Delivery Teste',
            'ativo' => 1,
        ]);
        DB::table('dlv_produtos')->insert([
            'id' => 777,
            'unidade_id' => 99,
            'nome' => 'Produto vinculado',
            'preco' => 10,
            'ativo' => 1,
            'visivel_loja' => 1,
            'ordem' => 0,
        ]);

        $this->withHeaders($this->headers())->getJson('/api/fidelidade/catalogo-consulta/produtos?unidade_id=1')
            ->assertOk()
            ->assertJsonPath('unidade_delivery_id', 99)
            ->assertJsonPath('loja_nome', 'Loja Delivery Teste')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.nome', 'Produto vinculado');
    }

    public function test_cartao_enrollment_normalizes_phone_and_cpf(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/fidelidade/cartoes', [
            'unidade_id' => 1,
            'telefone' => '+55 (69) 98463-9070',
            'cpf' => '529.982.247-25',
            'email' => 'Cliente@Example.COM',
            'nome' => 'Maria',
        ]);

        $response->assertCreated()
            ->assertJsonPath('conta.telefone_normalizado', '69984639070')
            ->assertJsonPath('conta.cpf_normalizado', '52998224725')
            ->assertJsonPath('conta.email', 'cliente@example.com')
            ->assertJsonPath('conta.status', 'ativo');

        $this->assertNotEmpty($response->json('conta.codigo_fidelidade'));
        $this->assertStringStartsWith('SAS-', $response->json('conta.codigo_fidelidade'));
    }

    public function test_selo_idempotency(): void
    {
        $this->seedPrograma(1, 10);
        $contaId = $this->criarCartao();

        $headers = array_merge($this->headers(), ['Idempotency-Key' => 'venda-abc-1']);

        $first = $this->withHeaders($headers)->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'descricao' => 'PDV',
        ])->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('conta.saldo_selos', 1)
            ->assertJsonPath('conta.saldo_pontos', 1);

        $this->withHeaders($headers)->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'descricao' => 'PDV',
        ])->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('conta.saldo_selos', 1)
            ->assertJsonPath('ledger.id', $first->json('ledger.id'));

        $this->assertDatabaseCount('fid_ledger', 2); // geracao + 1 selo
    }

    public function test_resgate_insuficiente_falha(): void
    {
        $this->seedPrograma(1, 5);
        $contaId = $this->criarCartao();
        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'idempotency_key' => 'selo-1',
        ])->assertCreated();

        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/resgatar", [])
            ->assertStatus(422);
    }

    public function test_resgate_sucesso(): void
    {
        $this->seedPrograma(1, 2);
        $contaId = $this->criarCartao();

        foreach (['a', 'b'] as $k) {
            $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
                'idempotency_key' => 'selo-'.$k,
            ])->assertCreated();
        }

        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/resgatar", [
            'idempotency_key' => 'redeem-1',
        ])->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('conta.saldo_selos', 0)
            ->assertJsonPath('resgate.status', 'pendente');

        $this->assertDatabaseCount('fid_resgates', 1);
    }

    public function test_estorno_restaura_saldo(): void
    {
        $this->seedPrograma(1, 10);
        $contaId = $this->criarCartao();

        $selo = $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'idempotency_key' => 'selo-x',
        ])->assertCreated();

        $ledgerId = (int) $selo->json('ledger.id');

        $this->withHeaders($this->headers())
            ->postJson("/api/fidelidade/cartoes/{$contaId}/estornar/{$ledgerId}", [
                'idempotency_key' => 'estorno-x',
            ])
            ->assertOk()
            ->assertJsonPath('conta.saldo_selos', 0)
            ->assertJsonPath('ledger.tipo', 'reversao');

        $this->assertDatabaseHas('fid_ledger', [
            'reverso_de_id' => $ledgerId,
            'tipo' => 'reversao',
        ]);
    }

    public function test_isolamento_unidade_e_permissao(): void
    {
        $this->seedPrograma(1, 5);
        $contaId = $this->criarCartao();

        $this->withHeaders($this->headers(3))
            ->getJson('/api/fidelidade/cartoes')
            ->assertForbidden();

        $this->withHeaders($this->headers(4))
            ->getJson('/api/fidelidade/cartoes')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->withHeaders($this->headers(2))
            ->getJson("/api/fidelidade/cartoes/{$contaId}")
            ->assertForbidden();

        $this->withHeaders($this->headers(2))->postJson('/api/fidelidade/cartoes', [
            'telefone' => '69999990000',
            'nome' => 'Outra unidade',
        ])->assertCreated()
            ->assertJsonPath('conta.unidade_id', 2);
    }

    public function test_relatorio_resumo(): void
    {
        $this->seedPrograma(1, 2);
        $contaId = $this->criarCartao();
        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'idempotency_key' => 's1',
        ]);
        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'idempotency_key' => 's2',
        ]);
        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/resgatar", [
            'idempotency_key' => 'r1',
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/fidelidade/relatorios/resumo?unidade_id=1')
            ->assertOk()
            ->assertJsonPath('resumo.cartoes_ativos', 1)
            ->assertJsonPath('resumo.selos_mes', 2)
            ->assertJsonPath('resumo.resgates_mes', 1)
            ->assertJsonPath('resumo.resgates_pendentes', 1);
    }

    public function test_excluir_cartao_recomeca_do_zero(): void
    {
        $this->seedPrograma(1, 5);
        $contaId = $this->criarCartao();
        $codigoAntigo = DB::table('fid_contas')->where('id', $contaId)->value('codigo_fidelidade');

        $this->withHeaders($this->headers())->postJson("/api/fidelidade/cartoes/{$contaId}/selo", [
            'idempotency_key' => 'selo-del',
        ])->assertCreated();

        $this->withHeaders($this->headers())
            ->deleteJson("/api/fidelidade/cartoes/{$contaId}")
            ->assertOk();

        $this->assertDatabaseMissing('fid_contas', ['id' => $contaId]);
        $this->assertDatabaseCount('fid_ledger', 0);
        $this->assertDatabaseCount('fid_resgates', 0);

        $novo = $this->withHeaders($this->headers())->postJson('/api/fidelidade/cartoes', [
            'unidade_id' => 1,
            'telefone' => '69988887777',
            'nome' => 'Cliente',
        ])->assertCreated()
            ->assertJsonPath('conta.saldo_selos', 0)
            ->assertJsonPath('conta.saldo_pontos', 0)
            ->assertJsonPath('reativado', false);

        $this->assertNotSame($codigoAntigo, $novo->json('conta.codigo_fidelidade'));
        $this->assertDatabaseCount('fid_ledger', 1); // só geracao
    }

    private function seedPrograma(int $unidadeId, int $meta): void
    {
        DB::table('fid_programas')->insert([
            'unidade_id' => $unidadeId,
            'ativo' => 1,
            'nome_exibicao' => 'Programa teste',
            'modo' => 'selos',
            'pedidos_meta' => $meta,
            'pontos_por_selo' => 1,
            'tipo_recompensa_padrao' => 'produto',
            'texto_recompensa' => 'Brinde',
            'permite_ajuste_manual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function criarCartao(): int
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/fidelidade/cartoes', [
            'unidade_id' => 1,
            'telefone' => '69988887777',
            'nome' => 'Cliente',
        ])->assertCreated();

        return (int) $response->json('conta.id');
    }
}
