<?php

namespace Tests\Feature;

use App\Services\Payments\DeliveryPixGatewayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryPixAutomaticoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/integracoes/webhooks/{provider}')) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/integrations_routes.php'));
        }
        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->getName() === 'delivery.public.payment.status')) {
            Route::middleware('web')->group(base_path('routes/web.php'));
        }

        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('usuarios');

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_180000_add_public_tracking_to_delivery_orders.php'))->up();
        (require database_path('migrations/2026_07_19_130000_add_vf_checkout_fields_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_150000_add_pix_confirmacao_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_160000_add_payment_gateway_to_delivery.php'))->up();

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => 8,
            'slug' => 'loja-gateway-test',
            'nome_loja' => 'Loja Gateway',
            'ativo' => 1,
            'aberta' => 1,
            'confirmar_pedidos' => 1,
            'exigir_pix_confirmado' => 0,
            'permite_retirada' => 1,
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 0,
            'formas_pagamento' => 'pix,dinheiro,cartao_online',
            'pix_chave' => 'pix@gateway.test',
            'pix_copia_cola' => '00020126580014br.gov.bcb.pix.manual',
            'pix_modo' => 'manual',
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

    public function test_iniciar_pix_modo_manual_nao_cria_externo(): void
    {
        $pedido = $this->pedidoPix(901);
        $config = DB::table('dlv_loja_config')->where('unidade_id', 8)->first();

        $service = app(DeliveryPixGatewayService::class);
        $result = $service->iniciarPix($pedido, $config);

        $this->assertSame('manual', $result['modo']);
        $this->assertFalse($result['automatico']);
        $this->assertSame('00020126580014br.gov.bcb.pix.manual', $result['payload']);
    }

    public function test_hibrido_faz_fallback_manual_quando_gateway_falha(): void
    {
        DB::table('dlv_loja_config')->where('unidade_id', 8)->update([
            'pix_modo' => 'hibrido',
            'pagamento_gateway' => 'mercado_pago',
            'pagamento_gateway_token' => 'TEST-TOKEN',
        ]);

        Http::fake([
            'api.mercadopago.com/*' => Http::response(['message' => 'Erro simulado'], 400),
        ]);

        $pedido = $this->pedidoPix(902);
        $config = DB::table('dlv_loja_config')->where('unidade_id', 8)->first();
        $result = app(DeliveryPixGatewayService::class)->iniciarPix($pedido, $config);

        $this->assertTrue($result['fallback_manual'] ?? false);
        $this->assertFalse($result['automatico']);
        $this->assertSame('00020126580014br.gov.bcb.pix.manual', $result['payload']);
    }

    public function test_webhook_confirma_pix_automatico(): void
    {
        DB::table('dlv_loja_config')->where('unidade_id', 8)->update([
            'pix_modo' => 'automatico',
            'pagamento_gateway' => 'mercado_pago',
            'pagamento_gateway_token' => 'TEST-TOKEN',
        ]);

        $token = str_repeat('d', 64);
        $this->insertOrder(903, 'pendente_loja', 'pendente', $token, [
            'pagamento_externo_id' => '99887766',
            'pagamento_externo_provedor' => 'mercado_pago',
            'pagamento_pix_payload' => '00020126580014br.gov.bcb.pix.dinamico',
        ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/99887766' => Http::response([
                'id' => 99887766,
                'status' => 'approved',
                'external_reference' => 'DLV-TEST-903',
            ], 200),
        ]);

        $this->postJson('/api/integracoes/webhooks/mercado_pago', [
            'type' => 'payment',
            'data' => ['id' => '99887766'],
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pedido_id', 903);

        $pedido = DB::table('dlv_pedidos')->where('id', 903)->first();
        $this->assertSame('pago', $pedido->pagamento_status);
        $this->assertSame('webhook', $pedido->pagamento_confirmado_origem);
    }

    public function test_poll_publico_confirma_pix_aprovado(): void
    {
        DB::table('dlv_loja_config')->where('unidade_id', 8)->update([
            'pix_modo' => 'automatico',
            'pagamento_gateway' => 'mercado_pago',
            'pagamento_gateway_token' => 'TEST-TOKEN',
        ]);

        $token = str_repeat('e', 64);
        $this->insertOrder(904, 'pendente_loja', 'pendente', $token, [
            'pagamento_externo_id' => '55443322',
            'pagamento_externo_provedor' => 'mercado_pago',
            'pagamento_pix_payload' => '00020126580014br.gov.bcb.pix.poll',
        ]);

        Http::fake([
            'api.mercadopago.com/v1/payments/55443322' => Http::response([
                'id' => 55443322,
                'status' => 'approved',
            ], 200),
        ]);

        $this->getJson('/loja/loja-gateway-test/pedido/DLV-TEST-904/'.$token.'/pagamento-status')
            ->assertOk()
            ->assertJsonPath('pix_pago', true)
            ->assertJsonPath('confirmado_agora', true);

        $this->assertSame('pago', DB::table('dlv_pedidos')->where('id', 904)->value('pagamento_status'));
    }

    public function test_formas_pagamento_inclui_cartao_online_quando_gateway_ativo(): void
    {
        DB::table('dlv_loja_config')->where('unidade_id', 8)->update([
            'pagamento_gateway' => 'mercado_pago',
            'pagamento_gateway_token' => 'TEST-TOKEN',
            'pagamento_online_ativo' => 1,
        ]);

        $config = DB::table('dlv_loja_config')->where('unidade_id', 8)->first();
        $formas = \App\Support\Delivery\DeliveryLojaCheckoutHelper::formasPagamentoLojaPublica($config);

        $this->assertArrayHasKey('cartao_online', $formas);
        $this->assertArrayHasKey('pix', $formas);
    }

    private function pedidoPix(int $id): object
    {
        $this->insertOrder($id, 'pendente_loja', 'pendente');

        return DB::table('dlv_pedidos')->where('id', $id)->first();
    }

    /** @param  array<string, mixed>  $extra */
    private function insertOrder(int $id, string $status, string $pagamentoStatus, ?string $clienteToken = null, array $extra = []): void
    {
        DB::table('dlv_pedidos')->insert(array_merge([
            'id' => $id,
            'unidade_id' => 8,
            'codigo_publico' => 'DLV-TEST-'.$id,
            'status' => $status,
            'canal' => 'loja',
            'fulfillment' => 'entrega',
            'cliente_nome' => 'Cliente Gateway',
            'cliente_telefone' => '91999997777',
            'cliente_token' => $clienteToken ?? str_repeat('a', 64),
            'entregador_token' => 'tok-'.$id,
            'pagamento_forma' => 'pix',
            'pagamento_status' => $pagamentoStatus,
            'subtotal' => 40,
            'frete_valor' => 0,
            'total' => 40,
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
