<?php

namespace Tests\Feature;

use App\Services\Delivery\DeliveryPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryPublicStorefrontTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_160000_add_estoque_to_delivery_products.php'))->up();
        (require database_path('migrations/2026_07_17_180000_add_public_tracking_to_delivery_orders.php'))->up();
    }

    protected function tearDown(): void
    {
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_storefront_returns_404_for_missing_or_inactive_store(): void
    {
        $this->get('/loja/inexistente')->assertNotFound();
        $this->store(['ativo' => false]);
        $this->get('/loja/teste')->assertNotFound();
    }

    public function test_catalog_only_publishes_active_visible_products_in_active_categories(): void
    {
        $this->store();
        $category = $this->category();
        $visible = $this->product($category, ['nome' => 'Produto público']);
        $this->product($category, ['nome' => 'Produto oculto', 'visivel_loja' => false]);
        $this->product($category, ['nome' => 'Produto inativo', 'ativo' => false]);
        $inactiveCategory = $this->category(['nome' => 'Categoria inativa', 'ativo' => false]);
        $this->product($inactiveCategory, ['nome' => 'Produto categoria inativa']);

        $this->get('/loja/teste')->assertOk()
            ->assertSee('Produto público')
            ->assertDontSee('Produto oculto')
            ->assertDontSee('Produto inativo')
            ->assertDontSee('Produto categoria inativa');
        $this->get("/loja/teste/produto/{$visible}")->assertOk();
    }

    public function test_product_detail_exposes_only_linked_active_options(): void
    {
        $this->store();
        $product = $this->product($this->category(), ['permite_adicionais' => true, 'acrescimo_escolhas_max' => 2]);
        $addition = DB::table('dlv_adicionais')->insertGetId($this->timestamps([
            'unidade_id' => 1, 'nome' => 'Bacon', 'tipo' => 'acrescentar', 'preco' => 4, 'ativo' => true, 'ordem' => 0,
        ]));
        DB::table('dlv_produto_adicional')->insert($this->timestamps(['produto_id' => $product, 'adicional_id' => $addition]));
        DB::table('dlv_produto_ingredientes')->insert($this->timestamps(['produto_id' => $product, 'nome' => 'Cebola', 'ordem' => 0]));

        $this->get("/loja/teste/produto/{$product}")->assertOk()->assertSee('Bacon')->assertSee('Cebola');
    }

    public function test_product_detail_personalizar_usa_stepper_e_limite_configurado(): void
    {
        $this->store();
        $product = $this->product($this->category(), [
            'permite_adicionais' => true,
            'acrescimo_escolhas_min' => 3,
            'acrescimo_escolhas_max' => 3,
            'acrescimos_loja_ui' => 'stepper',
            'ingredientes_retirar_ui' => 'stepper',
            'max_ingredientes_retirar' => 2,
        ]);

        foreach (['Arroz Paraense', 'Maniçoba com arroz', 'Açaí'] as $i => $nome) {
            $addition = DB::table('dlv_adicionais')->insertGetId($this->timestamps([
                'unidade_id' => 1, 'nome' => $nome, 'tipo' => 'acrescentar', 'preco' => 0, 'ativo' => true, 'ordem' => $i,
            ]));
            DB::table('dlv_produto_adicional')->insert($this->timestamps(['produto_id' => $product, 'adicional_id' => $addition]));
        }

        $this->get("/loja/teste/produto/{$product}")
            ->assertOk()
            ->assertSee('Personalizar')
            ->assertSee('Escolha 3 opções: Mínimo: 3 - Máximo: 3')
            ->assertSee('Pode repetir a mesma opção')
            ->assertSee('Compartilhar')
            ->assertSee('Sua nota')
            ->assertSee('Observação (opcional)')
            ->assertSee('option-stepper')
            ->assertSee('data-additional-plus')
            ->assertSee('0/3 selecionado(s)')
            ->assertDontSee('Adicionais');
    }

    public function test_checkout_recalculates_totals_decrements_stock_and_secures_tracking(): void
    {
        $this->store();
        $product = $this->product($this->category(), ['preco' => 20, 'estoque' => 3]);

        $response = $this->postJson('/loja/teste/checkout', $this->checkoutPayload($product, 2))
            ->assertCreated();
        $pedido = DB::table('dlv_pedidos')->first();

        $this->assertSame(40.0, (float) $pedido->subtotal);
        $this->assertSame(45.0, (float) $pedido->total);
        $this->assertSame(1, (int) DB::table('dlv_produtos')->where('id', $product)->value('estoque'));
        $this->assertSame('loja', $pedido->canal);
        $this->assertNotSame($pedido->entregador_token, $pedido->cliente_token);
        $this->get("/loja/teste/pedido/{$pedido->codigo_publico}/".str_repeat('0', 64))->assertNotFound();
        $this->get("/loja/teste/pedido/{$pedido->codigo_publico}/{$pedido->cliente_token}")
            ->assertOk()->assertSee($pedido->codigo_publico);
        $this->assertStringContainsString('/loja/teste/sucesso/', $response->json('redirect_url'));
    }

    public function test_checkout_rejects_insufficient_stock_and_unlinked_addition(): void
    {
        $this->store();
        $product = $this->product($this->category(), ['estoque' => 1, 'permite_adicionais' => true]);
        $addition = DB::table('dlv_adicionais')->insertGetId($this->timestamps([
            'unidade_id' => 1, 'nome' => 'Inválido', 'tipo' => 'acrescentar', 'preco' => 999, 'ativo' => true, 'ordem' => 0,
        ]));

        $this->postJson('/loja/teste/checkout', $this->checkoutPayload($product, 2))->assertUnprocessable();
        $payload = $this->checkoutPayload($product);
        $payload['itens'][0]['opcoes']['adicionais'] = [['id' => $addition, 'quantidade' => 1, 'preco' => 0]];
        $this->postJson('/loja/teste/checkout', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('dlv_pedidos', 0);
    }

    public function test_freight_and_pickup_follow_store_configuration(): void
    {
        $this->store(['permite_retirada' => false]);
        $product = $this->product($this->category());
        $items = $this->checkoutPayload($product)['itens'];

        $this->postJson('/loja/teste/frete', ['fulfillment' => 'entrega', 'cep' => '66000000', 'itens' => $items])
            ->assertOk()->assertJsonPath('frete_valor', 5);
        $this->postJson('/loja/teste/frete', ['fulfillment' => 'retirada', 'itens' => $items])
            ->assertUnprocessable();
    }

    public function test_cancellation_restores_delivery_stock_only_once(): void
    {
        $this->store();
        $product = $this->product($this->category(), ['estoque' => 2]);
        $this->postJson('/loja/teste/checkout', $this->checkoutPayload($product))->assertCreated();
        $pedido = DB::table('dlv_pedidos')->first();
        $service = app(DeliveryPedidoService::class);

        $service->alterarStatus($pedido, 'cancelado', null);
        $service->alterarStatus($pedido, 'cancelado', null);

        $this->assertSame(2, (int) DB::table('dlv_produtos')->where('id', $product)->value('estoque'));
        $this->assertNotNull(DB::table('dlv_pedidos')->where('id', $pedido->id)->value('estoque_restaurado_em'));
    }

    private function store(array $overrides = []): void
    {
        DB::table('dlv_loja_config')->insert($this->timestamps(array_merge([
            'unidade_id' => 1, 'slug' => 'teste', 'ativo' => true, 'aberta' => true,
            'confirmar_pedidos' => true, 'permite_retirada' => true, 'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 5, 'frete_gratis_acima' => null, 'frete_acrescimo_chuva_percent' => 0,
            'frete_chuva_ativa' => false, 'formas_pagamento' => 'dinheiro,pix', 'pix_chave' => 'pix@test',
            'nome_loja' => 'Loja Teste',
        ], $overrides)));
    }

    private function category(array $overrides = []): int
    {
        return DB::table('dlv_categorias')->insertGetId($this->timestamps(array_merge([
            'unidade_id' => 1, 'nome' => 'Lanches', 'ordem' => 0, 'ativo' => true,
        ], $overrides)));
    }

    private function product(int $category, array $overrides = []): int
    {
        return DB::table('dlv_produtos')->insertGetId($this->timestamps(array_merge([
            'unidade_id' => 1, 'categoria_id' => $category, 'nome' => 'Hambúrguer', 'preco' => 20,
            'estoque' => 5, 'ativo' => true, 'visivel_loja' => true, 'permite_adicionais' => false,
            'acrescimo_escolhas_min' => 0, 'acrescimo_escolhas_max' => null,
            'max_ingredientes_retirar' => 1, 'ingredientes_retirar_ui' => 'checkbox',
            'acrescimos_loja_ui' => 'stepper', 'ordem' => 0,
        ], $overrides)));
    }

    private function checkoutPayload(int $product, int $quantity = 1): array
    {
        return [
            'cliente_nome' => 'Cliente', 'cliente_telefone' => '91999999999',
            'fulfillment' => 'entrega', 'endereco_cep' => '66000000', 'endereco_rua' => 'Rua A',
            'endereco_numero' => '10', 'endereco_bairro' => 'Centro', 'endereco_cidade' => 'Belém',
            'endereco_uf' => 'PA', 'pagamento_forma' => 'dinheiro',
            'subtotal' => 0.01, 'total' => 0.01,
            'itens' => [['produto_id' => $product, 'quantidade' => $quantity, 'preco' => 0.01, 'opcoes' => []]],
        ];
    }

    private function timestamps(array $data): array
    {
        return array_merge($data, ['created_at' => now(), 'updated_at' => now()]);
    }

    private function tables(): array
    {
        return ['dlv_pedido_historico', 'dlv_pedido_itens', 'dlv_pedidos', 'dlv_entregadores',
            'dlv_frete_faixas_cep', 'dlv_produto_ingredientes', 'dlv_produto_adicional',
            'dlv_adicionais', 'dlv_produtos', 'dlv_categorias', 'dlv_loja_config'];
    }
}
