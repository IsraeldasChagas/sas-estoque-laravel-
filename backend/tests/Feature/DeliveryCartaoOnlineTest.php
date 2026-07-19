<?php

namespace Tests\Feature;

use App\Services\Payments\DeliveryCardGatewayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryCartaoOnlineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->getName() === 'delivery.public.success')) {
            Route::middleware('web')->group(base_path('routes/web.php'));
        }
        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/integracoes/webhooks/{provider}')) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/integrations_routes.php'));
        }

        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_180000_add_public_tracking_to_delivery_orders.php'))->up();
        (require database_path('migrations/2026_07_19_130000_add_vf_checkout_fields_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_150000_add_pix_confirmacao_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_160000_add_payment_gateway_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_170000_add_card_checkout_to_delivery.php'))->up();

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => 9,
            'slug' => 'loja-cartao-test',
            'nome_loja' => 'Loja Cartão',
            'ativo' => 1,
            'aberta' => 1,
            'confirmar_pedidos' => 1,
            'exigir_pix_confirmado' => 0,
            'permite_retirada' => 1,
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 0,
            'formas_pagamento' => 'pix,cartao_online,dinheiro',
            'pagamento_gateway' => 'mercado_pago',
            'pagamento_gateway_token' => 'TEST-TOKEN',
            'pagamento_online_ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_iniciar_checkout_cartao_gera_link(): void
    {
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'pref-123',
                'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-123',
                'sandbox_init_point' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-123',
            ], 201),
        ]);

        $pedido = $this->pedidoCartao(911);
        $config = DB::table('dlv_loja_config')->where('unidade_id', 9)->first();
        $result = app(DeliveryCardGatewayService::class)->iniciarCheckout($pedido, $config, [
            'success' => 'https://loja.test/sucesso',
            'failure' => 'https://loja.test/pedido',
            'pending' => 'https://loja.test/pedido',
        ]);

        $this->assertSame('cartao_online', $result['modo']);
        $this->assertTrue($result['automatico']);
        $this->assertStringContainsString('mercadopago', $result['checkout_url']);

        $atualizado = DB::table('dlv_pedidos')->where('id', 911)->first();
        $this->assertSame('pref-123', $atualizado->pagamento_externo_id);
        $this->assertNotNull($atualizado->pagamento_checkout_url);
    }

    public function test_webhook_confirma_cartao_online_por_referencia(): void
    {
        $token = str_repeat('b', 64);
        $this->insertOrder(912, 'pendente_loja', 'pendente', $token, [
            'pagamento_forma' => 'cartao_online',
            'pagamento_externo_id' => 'pref-999',
            'pagamento_externo_provedor' => 'mercado_pago',
            'pagamento_checkout_url' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-999',
        ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/777001' => Http::response([
                'id' => 777001,
                'status' => 'approved',
                'external_reference' => 'DLV-TEST-912',
            ], 200),
        ]);

        $this->postJson('/api/integracoes/webhooks/mercado_pago', [
            'type' => 'payment',
            'data' => ['id' => '777001'],
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pedido_id', 912);

        $this->assertSame('pago', DB::table('dlv_pedidos')->where('id', 912)->value('pagamento_status'));
    }

    public function test_sucesso_mostra_botao_pagar_cartao(): void
    {
        $token = str_repeat('a', 64);
        $this->insertOrder(913, 'pendente_loja', 'pendente', $token, [
            'pagamento_forma' => 'cartao_online',
            'pagamento_checkout_url' => 'https://sandbox.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-913',
        ]);

        $this->get('/loja/loja-cartao-test/sucesso/DLV-TEST-913/'.$token)
            ->assertOk()
            ->assertSee('Pagar com cartão agora')
            ->assertSee('Aguardando pagamento online');
    }

    private function pedidoCartao(int $id): object
    {
        $this->insertOrder($id, 'pendente_loja', 'pendente', null, [
            'pagamento_forma' => 'cartao_online',
        ]);

        return DB::table('dlv_pedidos')->where('id', $id)->first();
    }

    /** @param  array<string, mixed>  $extra */
    private function insertOrder(int $id, string $status, string $pagamentoStatus, ?string $clienteToken = null, array $extra = []): void
    {
        DB::table('dlv_pedidos')->insert(array_merge([
            'id' => $id,
            'unidade_id' => 9,
            'codigo_publico' => 'DLV-TEST-'.$id,
            'status' => $status,
            'canal' => 'loja',
            'fulfillment' => 'entrega',
            'cliente_nome' => 'Cliente Cartão',
            'cliente_telefone' => '91999996666',
            'cliente_token' => $clienteToken ?? str_repeat('a', 64),
            'entregador_token' => 'tok-'.$id,
            'pagamento_forma' => 'cartao_online',
            'pagamento_status' => $pagamentoStatus,
            'subtotal' => 50,
            'frete_valor' => 0,
            'total' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    /** @return list<string> */
    private function tables(): array
    {
        return [
            'dlv_pedido_historico', 'dlv_pedido_itens', 'dlv_pedidos', 'dlv_entregadores',
            'dlv_frete_faixas_cep', 'dlv_produto_ingredientes', 'dlv_produto_adicional',
            'dlv_adicionais', 'dlv_produtos', 'dlv_categorias', 'dlv_loja_config',
        ];
    }
}
