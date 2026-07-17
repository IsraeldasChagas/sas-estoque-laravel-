<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryOrdersUiApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/delivery/dashboard')) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
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

        DB::table('usuarios')->insert([
            'id' => 80,
            'nome' => 'Operador de testes',
            'perfil' => 'OPERADOR',
            'ativo' => true,
            'unidade_id' => 7,
            'permissoes_menu' => json_encode(['deliveryDashboard', 'deliveryPedidos']),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropDeliveryTables();
        Schema::dropIfExists('usuarios');

        parent::tearDown();
    }

    public function test_dashboard_exposes_vendafacil_metrics_and_seven_day_sales(): void
    {
        Carbon::setTestNow('2026-07-17 14:00:00');
        $this->insertProduct(701, 7, 'Produto A');
        $this->insertProduct(702, 7, 'Produto B');
        $this->insertProduct(801, 8, 'Outra unidade');

        $today = $this->insertOrder(710, 7, 'recebido', 58.50, now());
        $this->insertItem($today, 701, 2, 40);
        $this->insertItem($today, 702, 1, 18.50);

        $yesterday = $this->insertOrder(711, 7, 'entregue', 30, now()->subDay());
        $this->insertItem($yesterday, 701, 1, 30);

        $cancelled = $this->insertOrder(712, 7, 'cancelado', 100, now());
        $this->insertItem($cancelled, 701, 5, 100);
        $this->insertOrder(810, 8, 'entregue', 999, now());

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/delivery/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.vendas_hoje', 1)
            ->assertJsonPath('metrics.produtos_vendidos_hoje', 2)
            ->assertJsonPath('metrics.unidades_vendidas_hoje', 3)
            ->assertJsonPath('metrics.produtos_cadastrados', 2)
            ->assertJsonPath('metrics.venda_total', 88.5)
            ->assertJsonCount(7, 'seven_days')
            ->assertJsonStructure([
                'metrics' => [
                    'vendas_hoje',
                    'produtos_vendidos_hoje',
                    'unidades_vendidas_hoje',
                    'produtos_cadastrados',
                    'venda_total',
                ],
                'seven_days' => [['date', 'label', 'sales', 'total']],
                'resumo',
                'por_status',
                'ultimos',
            ]);

        $days = $response->json('seven_days');
        $this->assertSame('2026-07-17', $days[6]['date']);
        $this->assertSame(1, $days[6]['sales']);
        $this->assertSame(58.5, (float) $days[6]['total']);
        $this->assertSame(30.0, (float) $days[5]['total']);
    }

    public function test_orders_list_includes_ui_shape_and_respects_status_filter(): void
    {
        $this->insertOrder(720, 7, 'pendente_loja', 25, now(), 'whatsapp');
        $this->insertOrder(721, 7, 'entregue', 40, now()->subHour(), 'admin');
        $this->insertOrder(820, 8, 'pendente_loja', 90, now(), 'vitrine');

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/pedidos?status=pendente_loja')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', 720)
            ->assertJsonPath('items.0.canal', 'whatsapp')
            ->assertJsonPath('items.0.status', 'pendente_loja')
            ->assertJsonStructure([
                'items' => [[
                    'id',
                    'unidade_id',
                    'codigo_publico',
                    'status',
                    'canal',
                    'fulfillment',
                    'cliente_nome',
                    'subtotal',
                    'frete_valor',
                    'total',
                    'created_at',
                ]],
            ]);
    }

    private function headers(): array
    {
        return ['X-Usuario-Id' => '80'];
    }

    private function insertProduct(int $id, int $unitId, string $name): void
    {
        DB::table('dlv_produtos')->insert([
            'id' => $id,
            'unidade_id' => $unitId,
            'nome' => $name,
            'preco' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOrder(
        int $id,
        int $unitId,
        string $status,
        float $total,
        Carbon $createdAt,
        string $channel = 'admin',
    ): int {
        DB::table('dlv_pedidos')->insert([
            'id' => $id,
            'unidade_id' => $unitId,
            'codigo_publico' => 'DLV-TEST-'.$id,
            'status' => $status,
            'canal' => $channel,
            'fulfillment' => 'entrega',
            'cliente_nome' => 'Cliente '.$id,
            'subtotal' => $total,
            'frete_valor' => 0,
            'total' => $total,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    private function insertItem(int $orderId, int $productId, float $quantity, float $subtotal): void
    {
        DB::table('dlv_pedido_itens')->insert([
            'pedido_id' => $orderId,
            'produto_id' => $productId,
            'nome_produto' => 'Produto '.$productId,
            'quantidade' => $quantity,
            'preco_unitario' => $subtotal / $quantity,
            'preco_adicionais' => 0,
            'subtotal' => $subtotal,
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
