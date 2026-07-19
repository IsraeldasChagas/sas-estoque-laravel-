<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registrarRotasDelivery();
        $this->criarSchema();
        $this->seedUsuarios();
    }

    protected function tearDown(): void
    {
        foreach ($this->tabelas() as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    private function registrarRotasDelivery(): void
    {
        $existe = collect(Route::getRoutes())->contains(
            fn ($route) => str_contains('/'.$route->uri(), '/delivery/')
                || $route->uri() === 'api/delivery/dashboard'
                || $route->uri() === 'delivery/dashboard'
        );

        if (! $existe) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
        }
    }

    private function tabelas(): array
    {
        return [
            'dlv_pedido_historico',
            'dlv_pedido_itens',
            'dlv_pedidos',
            'dlv_entregadores',
            'dlv_frete_faixas_cep',
            'dlv_produto_ingredientes',
            'dlv_produto_adicional',
            'dlv_adicionais',
            'dlv_produtos',
            'dlv_categorias',
            'dlv_loja_config',
            'stock_lotes',
            'produtos',
            'usuarios',
        ];
    }

    private function criarSchema(): void
    {
        foreach ($this->tabelas() as $table) {
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

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->decimal('preco', 14, 2)->nullable();
        });

        Schema::create('stock_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('unidade_id');
            $table->decimal('quantidade', 14, 3)->default(0);
            $table->decimal('custo_unitario', 14, 2)->default(0);
        });

        $migration = require database_path('migrations/2026_07_17_150000_create_delivery_tables.php');
        $migration->up();

        $estoqueMigration = require database_path('migrations/2026_07_17_160000_add_estoque_to_delivery_products.php');
        $estoqueMigration->up();
    }

    private function seedUsuarios(): void
    {
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
                'nome' => 'Operador Unidade 1',
                'perfil' => 'OPERADOR',
                'ativo' => 1,
                'unidade_id' => 1,
                'permissoes_menu' => json_encode([
                    'deliveryDashboard', 'deliveryCatalogo', 'deliveryCategorias', 'deliveryProdutos',
                    'deliveryAdicionais', 'deliveryVitrine', 'deliveryPedidos', 'deliveryFretes',
                    'deliveryEntregadores', 'deliveryConfiguracoes',
                ]),
            ],
            [
                'id' => 3,
                'nome' => 'Operador Unidade 2',
                'perfil' => 'OPERADOR',
                'ativo' => 1,
                'unidade_id' => 2,
                'permissoes_menu' => json_encode([
                    'deliveryDashboard', 'deliveryCatalogo', 'deliveryCategorias', 'deliveryProdutos',
                    'deliveryAdicionais', 'deliveryVitrine', 'deliveryPedidos', 'deliveryFretes',
                    'deliveryEntregadores', 'deliveryConfiguracoes',
                ]),
            ],
        ]);
    }

    private function headers(int $usuarioId = 1): array
    {
        return ['X-Usuario-Id' => (string) $usuarioId];
    }

    private function bootstrapLoja(int $unidadeId = 1, array $extra = []): void
    {
        $this->withHeaders($this->headers())->putJson('/api/delivery/configuracoes?unidade_id='.$unidadeId, array_merge([
            'unidade_id' => $unidadeId,
            'slug' => 'loja-'.$unidadeId,
            'ativo' => true,
            'aberta' => true,
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 10,
            'frete_gratis_acima' => 100,
            'frete_acrescimo_chuva_percent' => 20,
            'frete_chuva_ativa' => false,
            'nome_loja' => 'Loja '.$unidadeId,
        ], $extra))->assertOk();
    }

    public function test_escopo_unidade_impede_acesso_cruzado(): void
    {
        $this->bootstrapLoja(1);
        $cat = $this->withHeaders($this->headers(2))->postJson('/api/delivery/categorias', [
            'nome' => 'Bebidas U1',
        ])->assertCreated()->json('id');

        $this->withHeaders($this->headers(3))
            ->getJson('/api/delivery/categorias/'.$cat)
            ->assertForbidden();

        $this->withHeaders($this->headers(3))
            ->getJson('/api/delivery/categorias')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_catalogo_crud_categorias_produtos_adicionais(): void
    {
        $this->bootstrapLoja(1);

        $categoria = $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => 1,
            'nome' => 'Lanches',
            'ordem' => 1,
        ])->assertCreated()->json();

        $adicional = $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => 1,
            'nome' => 'Bacon',
            'tipo' => 'acrescentar',
            'preco' => 5.5,
        ])->assertCreated()->json();

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'categoria_id' => $categoria['id'],
            'nome' => 'X-Burger',
            'sku' => 'XB-01',
            'preco' => 25,
            'visivel_loja' => true,
            'permite_adicionais' => true,
            'acrescimo_escolhas_min' => 0,
            'acrescimo_escolhas_max' => 3,
        ])->assertCreated()->json();

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/produtos/'.$produto['id'].'/adicionais', [
                'adicional_ids' => [$adicional['id']],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'adicionais');

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/catalogo?unidade_id=1&busca=Burger')
            ->assertOk()
            ->assertJsonPath('total_produtos', 1)
            ->assertJsonPath('produtos.0.nome', 'X-Burger');

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/catalogo?unidade_id=1&categoria_id='.$categoria['id'])
            ->assertOk()
            ->assertJsonPath('total_produtos', 1);

        $this->withHeaders($this->headers())
            ->putJson('/api/delivery/produtos/'.$produto['id'], ['preco' => 27])
            ->assertOk()
            ->assertJsonPath('preco', 27);

        $this->withHeaders($this->headers())
            ->deleteJson('/api/delivery/adicionais/'.$adicional['id'])
            ->assertOk();
    }

    public function test_soft_link_estoque_nao_muta_stock(): void
    {
        $this->bootstrapLoja(1);

        DB::table('produtos')->insert(['id' => 50, 'nome' => 'Estoque Burger', 'unidade_id' => 1, 'preco' => 10]);
        DB::table('stock_lotes')->insert([
            'id' => 1,
            'produto_id' => 50,
            'unidade_id' => 1,
            'quantidade' => 100,
            'custo_unitario' => 4,
        ]);

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'estoque_produto_id' => 50,
            'nome' => 'Burger Delivery',
            'preco' => 30,
            'permite_adicionais' => false,
        ])->assertCreated()->json();

        $this->assertSame(50, (int) $produto['estoque_produto_id']);

        $this->withHeaders($this->headers())->postJson('/api/delivery/pedidos', [
            'unidade_id' => 1,
            'cliente_nome' => 'Cliente Soft',
            'fulfillment' => 'retirada',
            'itens' => [
                ['produto_id' => $produto['id'], 'quantidade' => 2],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('produtos', ['id' => 50, 'nome' => 'Estoque Burger', 'preco' => 10]);
        $this->assertDatabaseHas('stock_lotes', ['id' => 1, 'produto_id' => 50, 'quantidade' => 100]);
    }

    public function test_pedido_snapshot_adicionais_e_totais(): void
    {
        $this->bootstrapLoja(1, ['frete_taxa_fixa' => 8, 'frete_gratis_acima' => null]);

        $adicional = $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => 1,
            'nome' => 'Queijo Extra',
            'preco' => 4,
            'tipo' => 'acrescentar',
        ])->assertCreated()->json();

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Pizza',
            'preco' => 40,
            'permite_adicionais' => true,
            'acrescimo_escolhas_min' => 1,
            'acrescimo_escolhas_max' => 2,
        ])->assertCreated()->json();

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/produtos/'.$produto['id'].'/adicionais', [
                'adicional_ids' => [$adicional['id']],
            ])->assertOk();

        $pedido = $this->withHeaders($this->headers())->postJson('/api/delivery/pedidos', [
            'unidade_id' => 1,
            'cliente_nome' => 'Maria',
            'fulfillment' => 'entrega',
            'endereco_cep' => '66000000',
            'itens' => [[
                'produto_id' => $produto['id'],
                'quantidade' => 1,
                'opcoes' => [
                    'adicionais' => [['id' => $adicional['id'], 'quantidade' => 2]],
                ],
            ]],
        ])->assertCreated()->json();

        $this->assertSame(48.0, (float) $pedido['subtotal']); // 40 + (4*2)
        $this->assertSame(8.0, (float) $pedido['frete_valor']);
        $this->assertSame(56.0, (float) $pedido['total']);
        $this->assertSame(8.0, (float) $pedido['itens'][0]['preco_adicionais']);
        $this->assertSame('Queijo Extra', $pedido['itens'][0]['opcoes']['adicionais'][0]['nome']);
        $this->assertSame(4.0, (float) $pedido['itens'][0]['opcoes']['adicionais'][0]['preco']);
        $this->assertSame(2, (int) $pedido['itens'][0]['opcoes']['adicionais'][0]['quantidade']);
    }

    public function test_frete_fixo_cep_gratis_e_chuva(): void
    {
        $this->bootstrapLoja(1, [
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 12,
            'frete_gratis_acima' => 50,
            'frete_acrescimo_chuva_percent' => 25,
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/fretes/calcular', [
                'unidade_id' => 1,
                'fulfillment' => 'retirada',
                'subtotal' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('frete_valor', 0);

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/fretes/calcular', [
                'unidade_id' => 1,
                'fulfillment' => 'entrega',
                'subtotal' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('frete_valor', 12)
            ->assertJsonPath('frete_gratis', false);

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/fretes/calcular', [
                'unidade_id' => 1,
                'fulfillment' => 'entrega',
                'subtotal' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('frete_valor', 0)
            ->assertJsonPath('frete_gratis', true);

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/fretes/calcular', [
                'unidade_id' => 1,
                'fulfillment' => 'entrega',
                'subtotal' => 20,
                'chuva' => true,
            ])
            ->assertOk()
            ->assertJsonPath('frete_valor', 15); // 12 * 1.25

        $this->withHeaders($this->headers())->putJson('/api/delivery/configuracoes?unidade_id=1', [
            'unidade_id' => 1,
            'frete_modo' => 'cep_band',
        ])->assertOk();

        $this->withHeaders($this->headers())->postJson('/api/delivery/fretes/faixas', [
            'unidade_id' => 1,
            'cep_inicio' => '66000000',
            'cep_fim' => '66099999',
            'taxa' => 18,
            'label' => 'Centro',
        ])->assertCreated();

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/fretes/calcular', [
                'unidade_id' => 1,
                'cep' => '66012-345',
                'subtotal' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('frete_valor', 18)
            ->assertJsonPath('modo', 'cep_band');

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/fretes/calcular', [
                'unidade_id' => 1,
                'cep' => '67000000',
                'subtotal' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('bloqueado', false)
            ->assertJsonPath('frete_valor', 12);
    }

    public function test_status_transicoes_validas_invalidas_e_historico(): void
    {
        $this->bootstrapLoja(1, ['frete_taxa_fixa' => 0, 'frete_gratis_acima' => null]);

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Suco',
            'preco' => 10,
        ])->assertCreated()->json();

        $pedido = $this->withHeaders($this->headers())->postJson('/api/delivery/pedidos', [
            'unidade_id' => 1,
            'cliente_nome' => 'João',
            'fulfillment' => 'retirada',
            'itens' => [['produto_id' => $produto['id'], 'quantidade' => 1]],
        ])->assertCreated()->json();

        $id = $pedido['id'];
        $this->assertCount(1, $pedido['historico']);

        $this->withHeaders($this->headers())
            ->patchJson("/api/delivery/pedidos/{$id}/status", ['status' => 'rota'])
            ->assertStatus(422);

        foreach (['recebido', 'preparo', 'pronto', 'entregue'] as $status) {
            $this->withHeaders($this->headers())
                ->patchJson("/api/delivery/pedidos/{$id}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('status', $status);
        }

        $this->withHeaders($this->headers())
            ->patchJson("/api/delivery/pedidos/{$id}/status", ['status' => 'cancelado'])
            ->assertStatus(422);

        $show = $this->withHeaders($this->headers())
            ->getJson("/api/delivery/pedidos/{$id}")
            ->assertOk()
            ->json();

        $this->assertSame('entregue', $show['status']);
        $this->assertGreaterThanOrEqual(5, count($show['historico']));
        $this->assertDatabaseHas('dlv_pedido_historico', [
            'pedido_id' => $id,
            'status_novo' => 'entregue',
            'acao' => 'status_alterado',
        ]);
    }

    public function test_dashboard_resumo(): void
    {
        $this->bootstrapLoja(1, ['frete_taxa_fixa' => 0]);

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Combo',
            'preco' => 20,
        ])->assertCreated()->json();

        $pedido = $this->withHeaders($this->headers())->postJson('/api/delivery/pedidos', [
            'unidade_id' => 1,
            'cliente_nome' => 'Ana',
            'fulfillment' => 'retirada',
            'itens' => [['produto_id' => $produto['id'], 'quantidade' => 1]],
        ])->assertCreated()->json();

        $this->withHeaders($this->headers())
            ->patchJson('/api/delivery/pedidos/'.$pedido['id'].'/status', ['status' => 'recebido'])
            ->assertOk();

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/dashboard?unidade_id=1')
            ->assertOk()
            ->assertJsonPath('resumo.total_pedidos', 1)
            ->assertJsonPath('resumo.pendente_loja', 0)
            ->assertJsonPath('por_status.recebido', 1)
            ->assertJsonCount(1, 'ultimos');
    }

    public function test_vitrine_e_entregadores(): void
    {
        $this->bootstrapLoja(1);

        $this->withHeaders($this->headers())
            ->putJson('/api/delivery/vitrine?unidade_id=1', [
                'unidade_id' => 1,
                'nome_loja' => 'Sabor Delivery',
                'banner_path' => 'uploads/delivery/banner.jpg',
                'aberta' => true,
            ])
            ->assertOk()
            ->assertJsonPath('nome_loja', 'Sabor Delivery')
            ->assertJsonPath('preview_path', '/loja/loja-1');

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/entregadores', [
                'unidade_id' => 1,
                'nome' => 'Carlos',
                'whatsapp' => '69999999999',
            ])
            ->assertCreated()
            ->assertJsonPath('nome', 'Carlos');

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/entregadores?unidade_id=1')
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }

    public function test_produto_gera_sku_automatico_quando_omitido(): void
    {
        $this->bootstrapLoja(1);

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Auto SKU',
            'preco' => 12,
            'estoque' => 5,
        ])->assertCreated()->json();

        $this->assertMatchesRegularExpression('/^CI-[A-Z0-9]{8}$/', (string) $produto['sku']);
        $this->assertSame(5, (int) $produto['estoque']);

        $this->withHeaders($this->headers())
            ->putJson('/api/delivery/produtos/'.$produto['id'], ['nome' => 'Auto SKU 2', 'sku' => 'HACK-01'])
            ->assertOk()
            ->assertJsonPath('sku', $produto['sku']);
    }

    public function test_produto_payload_completo_ingredientes_e_adicionais(): void
    {
        $this->bootstrapLoja(1);

        $categoria = $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => 1,
            'nome' => 'Burgers',
        ])->assertCreated()->json();

        $adicional = $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => 1,
            'nome' => 'Bacon',
            'tipo' => 'acrescentar',
            'preco' => 4,
        ])->assertCreated()->json();

        $retirar = $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => 1,
            'nome' => 'Sem cebola (legado)',
            'tipo' => 'retirar',
            'preco' => 0,
        ])->assertCreated()->json();

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'categoria_id' => $categoria['id'],
            'nome' => 'X-Tudo inválido',
            'preco' => 32,
            'estoque' => 10,
            'permite_adicionais' => true,
            'acrescimo_escolhas_min' => 0,
            'acrescimo_escolhas_max' => 2,
            'adicional_ids' => [$adicional['id'], $retirar['id']],
            'ingredientes' => [
                ['nome' => 'Alface'],
                ['nome' => 'Tomate'],
            ],
            'max_ingredientes_retirar' => 1,
        ])->assertStatus(422);

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'categoria_id' => $categoria['id'],
            'nome' => 'X-Tudo',
            'preco' => 32,
            'estoque' => 10,
            'permite_adicionais' => true,
            'acrescimo_escolhas_min' => 0,
            'acrescimo_escolhas_max' => 2,
            'acrescimos_loja_ui' => 'stepper',
            'ingredientes_retirar_ui' => 'checkbox',
            'max_ingredientes_retirar' => 1,
            'adicional_ids' => [$adicional['id']],
            'ingredientes' => [
                ['nome' => 'Alface'],
                ['nome' => ''],
                ['nome' => 'Tomate'],
            ],
        ])->assertCreated()->json();

        $this->assertCount(1, $produto['adicionais']);
        $this->assertCount(2, $produto['ingredientes']);
        $this->assertSame('Alface', $produto['ingredientes'][0]['nome']);
        $this->assertSame('Tomate', $produto['ingredientes'][1]['nome']);
        $this->assertSame(0, (int) $produto['ingredientes'][0]['ordem']);
        $this->assertSame(1, (int) $produto['ingredientes'][1]['ordem']);

        $atualizado = $this->withHeaders($this->headers())->putJson('/api/delivery/produtos/'.$produto['id'], [
            'permite_adicionais' => false,
            'ingredientes' => [
                ['nome' => 'Queijo', 'id' => $produto['ingredientes'][0]['id']],
            ],
            'max_ingredientes_retirar' => 0,
            'ingredientes_retirar_ui' => 'stepper',
        ])->assertOk()->json();

        $this->assertFalse((bool) $atualizado['permite_adicionais']);
        $this->assertCount(0, $atualizado['adicionais']);
        $this->assertSame(0, (int) $atualizado['acrescimo_escolhas_min']);
        $this->assertNull($atualizado['acrescimo_escolhas_max']);
        $this->assertCount(1, $atualizado['ingredientes']);
        $this->assertSame('Queijo', $atualizado['ingredientes'][0]['nome']);
    }

    public function test_produto_validacoes_ui_minmax_e_lista_filtros(): void
    {
        $this->bootstrapLoja(1);

        $catA = $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => 1,
            'nome' => 'Doces',
        ])->assertCreated()->json();

        $catB = $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => 1,
            'nome' => 'Salgados',
        ])->assertCreated()->json();

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'categoria_id' => $catA['id'],
            'nome' => 'Brigadeiro',
            'sku' => 'DOC-01',
            'preco' => 5,
            'ativo' => true,
            'estoque' => 3,
        ])->assertCreated();

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'categoria_id' => $catB['id'],
            'nome' => 'Coxinha',
            'sku' => 'SAL-01',
            'preco' => 8,
            'ativo' => false,
            'estoque' => 0,
        ])->assertCreated();

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/produtos?unidade_id=1&q=Doces')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.nome', 'Brigadeiro')
            ->assertJsonPath('items.0.categoria_nome', 'Doces')
            ->assertJsonPath('items.0.estoque', 3);

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/produtos?unidade_id=1&busca=SAL-01')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.nome', 'Coxinha');

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/produtos?unidade_id=1&ativo=false')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.nome', 'Coxinha');

        $lista = $this->withHeaders($this->headers())
            ->getJson('/api/delivery/produtos?unidade_id=1')
            ->assertOk()
            ->json('items');
        $this->assertSame(['Brigadeiro', 'Coxinha'], array_column($lista, 'nome'));

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Inválido UI',
            'preco' => 1,
            'ingredientes_retirar_ui' => 'radio',
        ])->assertStatus(422);

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Inválido minmax',
            'preco' => 1,
            'permite_adicionais' => true,
            'acrescimo_escolhas_min' => 5,
            'acrescimo_escolhas_max' => 2,
        ])->assertStatus(422);

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Sem max retirar',
            'preco' => 1,
            'ingredientes' => [['nome' => 'Cebola']],
        ])->assertStatus(422);

        $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Max retirar alto',
            'preco' => 1,
            'ingredientes' => [['nome' => 'Cebola']],
            'max_ingredientes_retirar' => 3,
        ])->assertStatus(422);
    }

    public function test_produto_foto_base64_upload_e_cleanup(): void
    {
        $this->bootstrapLoja(1);

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $produto = $this->withHeaders($this->headers())->postJson('/api/delivery/produtos', [
            'unidade_id' => 1,
            'nome' => 'Com Foto',
            'preco' => 15,
            'estoque' => 2,
            'foto_base64' => $png,
            'ingredientes' => [
                ['nome' => 'Molho', 'foto_base64' => $png],
            ],
            'max_ingredientes_retirar' => 1,
            'ingredientes_retirar_ui' => 'stepper',
        ])->assertCreated()->json();

        $this->assertNotEmpty($produto['foto_path']);
        $this->assertSame('/'.$produto['foto_path'], $produto['foto_url']);
        $this->assertFileExists(public_path($produto['foto_path']));
        $this->assertNotEmpty($produto['ingredientes'][0]['foto_path']);
        $this->assertFileExists(public_path($produto['ingredientes'][0]['foto_path']));

        $catalogo = $this->withHeaders($this->headers())
            ->getJson('/api/delivery/catalogo?unidade_id=1')
            ->assertOk()
            ->json();
        $this->assertSame(2, (int) $catalogo['produtos'][0]['estoque']);
        $this->assertSame($produto['foto_url'], $catalogo['produtos'][0]['foto_url']);

        $oldProdutoFoto = $produto['foto_path'];
        $oldIngFoto = $produto['ingredientes'][0]['foto_path'];

        $atualizado = $this->withHeaders($this->headers())->putJson('/api/delivery/produtos/'.$produto['id'], [
            'foto_base64' => $png,
            'ingredientes' => [
                [
                    'nome' => 'Molho especial',
                    'foto_path' => $oldIngFoto,
                ],
            ],
            'max_ingredientes_retirar' => 0,
        ])->assertOk()->json();

        $this->assertNotSame($oldProdutoFoto, $atualizado['foto_path']);
        $this->assertFileDoesNotExist(public_path($oldProdutoFoto));
        $this->assertFileExists(public_path($oldIngFoto));
        $this->assertSame($oldIngFoto, $atualizado['ingredientes'][0]['foto_path']);

        $this->withHeaders($this->headers())
            ->deleteJson('/api/delivery/produtos/'.$produto['id'])
            ->assertOk();

        $this->assertFileDoesNotExist(public_path($atualizado['foto_path']));
        $this->assertFileDoesNotExist(public_path($oldIngFoto));
    }
}
