<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryPedidoEntregadorCupomTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/delivery/pedidos/pendentes-poll')) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
        }
        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->getName() === 'delivery.public.entregador.show')) {
            Route::middleware('web')->group(base_path('routes/web.php'));
        }

        $this->dropDeliveryTables();
        Schema::dropIfExists('usuarios');
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->json('permissoes_menu')->nullable();
        });

        $migration = require database_path('migrations/2026_07_17_150000_create_delivery_tables.php');
        $migration->up();
        $trackingMigration = require database_path('migrations/2026_07_17_180000_add_public_tracking_to_delivery_orders.php');
        $trackingMigration->up();

        DB::table('usuarios')->insert([
            'id' => 90,
            'nome' => 'Operador entregador',
            'perfil' => 'OPERADOR',
            'ativo' => true,
            'unidade_id' => 7,
            'permissoes_menu' => json_encode(['deliveryPedidos', 'deliveryEntregadores']),
        ]);

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => 7,
            'slug' => 'loja-teste',
            'ativo' => true,
            'aberta' => true,
            'confirmar_pedidos' => true,
            'nome_loja' => 'Sabor Teste',
            'whatsapp' => '(91) 99999-0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dlv_entregadores')->insert([
            'id' => 501,
            'unidade_id' => 7,
            'nome' => 'João Entregador',
            'whatsapp' => '91988887777',
            'foto_path' => 'uploads/delivery/entregadores/7/foto-teste.png',
            'ativo' => 1,
            'ordem' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fotoDir = public_path('uploads/delivery/entregadores/7');
        if (! is_dir($fotoDir)) {
            mkdir($fotoDir, 0755, true);
        }
        file_put_contents(public_path('uploads/delivery/entregadores/7/foto-teste.png'), base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    protected function tearDown(): void
    {
        $this->dropDeliveryTables();
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_show_pedido_inclui_entregadores_urls_e_cupom(): void
    {
        $this->insertOrder(901, 'pendente_loja', 'entregador-token-abc', 'cliente-token-1234567890123456789012345678901234567890123456789012345678901234');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/delivery/pedidos/901')
            ->assertOk();

        $response->assertJsonPath('entregadores.0.nome', 'João Entregador')
            ->assertJsonPath('entregadores.0.whatsapp_url', 'https://wa.me/5591988887777')
            ->assertJsonStructure(['url_imprimir', 'url_entregador', 'cupom_whatsapp_url', 'status_rotulo']);

        $fotoUrl = $response->json('entregadores.0.foto_url');
        $this->assertNotNull($fotoUrl);
        $this->assertStringContainsString('/uploads/delivery/entregadores/7/foto-teste.png', $fotoUrl);
        $this->assertStringContainsString('wa.me/', $response->json('cliente_whatsapp_url'));

        $this->assertStringContainsString('/loja/loja-teste/entrega/', $response->json('url_entregador'));
    }

    public function test_poll_pendentes_retorna_enabled_quando_confirmar_pedidos_ativo(): void
    {
        $this->insertOrder(902, 'pendente_loja', 'tok1');
        $this->insertOrder(903, 'recebido', 'tok2');

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/pedidos/pendentes-poll')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonCount(1, 'pedidos')
            ->assertJsonPath('pedidos.0.id', 902);
    }

    public function test_imprimir_retorna_html_do_cupom(): void
    {
        $this->insertOrder(904, 'recebido', 'tok3');

        $response = $this->withHeaders($this->headers())
            ->get('/api/delivery/pedidos/904/imprimir');

        $response->assertOk();
        $this->assertStringContainsString('Cupom fiscal simplificado / comanda', $response->getContent());
        $this->assertStringContainsString('DLV-TEST-904', $response->getContent());
    }

    public function test_entregador_publico_marca_pedido_como_entregue(): void
    {
        $this->insertOrder(905, 'rota', 'entregador-publico-token');

        $this->post('/loja/loja-teste/entrega/DLV-TEST-905/entregador-publico-token', [
            'resultado' => 'entregue',
        ])->assertRedirect();

        $this->assertSame('entregue', DB::table('dlv_pedidos')->where('id', 905)->value('status'));
    }

    public function test_entregador_publico_rejeita_token_invalido(): void
    {
        $this->insertOrder(906, 'rota', 'token-correto');

        $this->get('/loja/loja-teste/entrega/DLV-TEST-906/token-errado')->assertNotFound();
    }

    public function test_status_update_retorna_whatsapp_aviso_para_pedido_loja(): void
    {
        $this->insertOrder(907, 'recebido', 'tok-status', str_repeat('a', 64));

        $response = $this->withHeaders($this->headers())
            ->patchJson('/api/delivery/pedidos/907/status', ['status' => 'preparo'])
            ->assertOk();

        $url = $response->json('whatsapp_aviso_url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('wa.me/', $url);
        $this->assertStringContainsString('text=', $url);
    }

    public function test_decisao_pendente_aceitar_retorna_proximo_e_para_fluxo(): void
    {
        $this->insertOrder(908, 'pendente_loja', 'tok-a');
        $this->insertOrder(909, 'pendente_loja', 'tok-b');

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/delivery/pedidos/908/pendente', ['decisao' => 'aceitar'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('recebido', DB::table('dlv_pedidos')->where('id', 908)->value('status'));
        $this->assertSame(909, $response->json('proximo.id'));
        $this->assertArrayHasKey('pendente_post_url', $response->json('proximo'));
    }

    private function headers(): array
    {
        return ['X-Usuario-Id' => '90'];
    }

    private function insertOrder(int $id, string $status, string $entregadorToken, ?string $clienteToken = null): void
    {
        $data = [
            'id' => $id,
            'unidade_id' => 7,
            'codigo_publico' => 'DLV-TEST-'.$id,
            'status' => $status,
            'canal' => 'loja',
            'fulfillment' => 'entrega',
            'cliente_nome' => 'Maria Cliente',
            'cliente_telefone' => '91977776666',
            'cliente_whatsapp' => '91977776666',
            'endereco_texto' => 'Rua Teste, 100',
            'endereco_cep' => '66000000',
            'subtotal' => 45,
            'frete_valor' => 5,
            'total' => 50,
            'entregador_token' => $entregadorToken,
            'pagamento_forma' => 'pix',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($clienteToken !== null) {
            $data['cliente_token'] = $clienteToken;
        }
        DB::table('dlv_pedidos')->insert($data);

        DB::table('dlv_pedido_itens')->insert([
            'pedido_id' => $id,
            'produto_id' => null,
            'nome_produto' => 'Tacacá',
            'quantidade' => 1,
            'preco_unitario' => 45,
            'preco_adicionais' => 0,
            'subtotal' => 45,
            'ordem' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropDeliveryTables(): void
    {
        foreach ([
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
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
