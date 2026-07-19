<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryPixConfirmacaoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/delivery/pedidos/pendentes-poll')) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
        }
        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->getName() === 'delivery.public.success')) {
            Route::middleware('web')->group(base_path('routes/web.php'));
        }

        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('usuarios');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->json('permissoes_menu')->nullable();
        });

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_160000_add_estoque_to_delivery_products.php'))->up();
        (require database_path('migrations/2026_07_17_180000_add_public_tracking_to_delivery_orders.php'))->up();
        (require database_path('migrations/2026_07_19_130000_add_vf_checkout_fields_to_delivery.php'))->up();
        (require database_path('migrations/2026_07_19_150000_add_pix_confirmacao_to_delivery.php'))->up();

        DB::table('usuarios')->insert([
            'id' => 90,
            'nome' => 'Operador PIX',
            'perfil' => 'OPERADOR',
            'ativo' => true,
            'unidade_id' => 7,
            'permissoes_menu' => json_encode(['deliveryPedidos', 'deliveryConfiguracoes']),
        ]);

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => 7,
            'slug' => 'loja-pix-test',
            'nome_loja' => 'Loja PIX',
            'ativo' => 1,
            'aberta' => 1,
            'confirmar_pedidos' => 1,
            'exigir_pix_confirmado' => 1,
            'permite_retirada' => 1,
            'frete_modo' => 'fixed',
            'frete_taxa_fixa' => 0,
            'formas_pagamento' => 'pix,dinheiro',
            'pix_chave' => 'pix@teste.com',
            'pix_copia_cola' => '00020126580014br.gov.bcb.pix',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_confirmar_pagamento_pix_marca_como_pago(): void
    {
        $this->insertOrder(801, 'pendente_loja', 'pendente');

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/pedidos/801/pagamento/confirmar')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pagamento_status', 'pago')
            ->assertJsonPath('pix_pago', true);

        $pedido = DB::table('dlv_pedidos')->where('id', 801)->first();
        $this->assertSame('pago', $pedido->pagamento_status);
        $this->assertNotNull($pedido->pagamento_confirmado_em);
    }

    public function test_bloqueia_aceite_quando_exigir_pix_confirmado(): void
    {
        $this->insertOrder(802, 'pendente_loja', 'pendente');

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/pedidos/802/pendente', ['decisao' => 'aceitar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pagamento']);

        $this->assertSame('pendente_loja', DB::table('dlv_pedidos')->where('id', 802)->value('status'));
    }

    public function test_aceita_apos_confirmar_pix(): void
    {
        $this->insertOrder(803, 'pendente_loja', 'pendente');

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/pedidos/803/pagamento/confirmar')
            ->assertOk();

        $this->withHeaders($this->headers())
            ->postJson('/api/delivery/pedidos/803/pendente', ['decisao' => 'aceitar'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('recebido', DB::table('dlv_pedidos')->where('id', 803)->value('status'));
    }

    public function test_sucesso_mostra_status_pix_pendente(): void
    {
        $token = str_repeat('b', 64);
        $this->insertOrder(804, 'pendente_loja', 'pendente', $token);

        $this->get('/loja/loja-pix-test/sucesso/DLV-TEST-804/'.$token)
            ->assertOk()
            ->assertSee('Aguardando confirmação do PIX')
            ->assertSee('Pix copia e cola');
    }

    public function test_acompanhar_pedido_mostra_pix_confirmado(): void
    {
        $token = str_repeat('c', 64);
        $this->insertOrder(805, 'recebido', 'pago', $token);

        $this->get('/loja/loja-pix-test/pedido/DLV-TEST-805/'.$token)
            ->assertOk()
            ->assertSee('PIX confirmado');
    }

    private function headers(): array
    {
        return ['X-Usuario-Id' => '90'];
    }

    private function insertOrder(int $id, string $status, string $pagamentoStatus, ?string $clienteToken = null): void
    {
        DB::table('dlv_pedidos')->insert([
            'id' => $id,
            'unidade_id' => 7,
            'codigo_publico' => 'DLV-TEST-'.$id,
            'status' => $status,
            'canal' => 'loja',
            'fulfillment' => 'entrega',
            'cliente_nome' => 'Cliente PIX',
            'cliente_telefone' => '91999998888',
            'cliente_token' => $clienteToken ?? str_repeat('a', 64),
            'entregador_token' => 'tok-'.$id,
            'pagamento_forma' => 'pix',
            'pagamento_status' => $pagamentoStatus,
            'subtotal' => 30,
            'frete_valor' => 5,
            'total' => 35,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tables(): array
    {
        return [
            'dlv_pedido_historico', 'dlv_pedido_itens', 'dlv_pedidos', 'dlv_entregadores',
            'dlv_frete_faixas_cep', 'dlv_produto_ingredientes', 'dlv_produto_adicional',
            'dlv_adicionais', 'dlv_produtos', 'dlv_categorias', 'dlv_loja_config',
        ];
    }
}
