<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryLogisticsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/delivery/fretes/calcular')) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
        }

        $this->dropSchema();
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->json('permissoes_menu')->nullable();
        });

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_171000_improve_delivery_drivers.php'))->up();

        DB::table('usuarios')->insert([
            [
                'id' => 11, 'nome' => 'Operador 1', 'perfil' => 'OPERADOR', 'ativo' => 1, 'unidade_id' => 1,
                'permissoes_menu' => json_encode(['deliveryFretes', 'deliveryEntregadores']),
            ],
            [
                'id' => 12, 'nome' => 'Operador 2', 'perfil' => 'OPERADOR', 'ativo' => 1, 'unidade_id' => 2,
                'permissoes_menu' => json_encode(['deliveryFretes', 'deliveryEntregadores']),
            ],
        ]);

        DB::table('dlv_loja_config')->insert([
            [
                'unidade_id' => 1, 'slug' => 'logistica-1', 'frete_modo' => 'fixed',
                'frete_taxa_fixa' => 9.5, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'unidade_id' => 2, 'slug' => 'logistica-2', 'frete_modo' => 'fixed',
                'frete_taxa_fixa' => 7, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    public function test_calculo_aceita_endereco_e_expoe_label_e_message(): void
    {
        $this->withHeaders($this->headers(11))
            ->postJson('/api/delivery/fretes/calcular', [
                'cep' => '66012-345',
                'subtotal' => 20,
                'logradouro' => 'Avenida Nazaré',
                'numero' => '100',
                'bairro' => 'Nazaré',
                'cidade' => 'Belém',
                'uf' => 'PA',
                'complemento' => 'Sala 1',
                'telefone' => '91999999999',
            ])
            ->assertOk()
            ->assertJsonPath('frete_valor', 9.5)
            ->assertJsonPath('label', 'Taxa fixa da loja (modo sem faixas)')
            ->assertJsonPath('message', 'Frete taxa fixa.')
            ->assertJsonPath('mensagem', 'Frete taxa fixa.');

        DB::table('dlv_loja_config')->where('unidade_id', 1)->update(['frete_modo' => 'cep_band']);
        DB::table('dlv_frete_faixas_cep')->insert([
            'unidade_id' => 1, 'cep_inicio' => '66000000', 'cep_fim' => '66099999',
            'taxa' => 14, 'label' => 'Centro expandido', 'ativo' => 1, 'ordem' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withHeaders($this->headers(11))
            ->postJson('/api/delivery/fretes/calcular', ['cep' => '67000-000'])
            ->assertOk()
            ->assertJsonPath('bloqueado', false)
            ->assertJsonPath('frete_valor', 9.5)
            ->assertJsonPath('label', 'Taxa padrão da loja');
    }

    public function test_entregador_exige_whatsapp_respeita_limites_e_escopo(): void
    {
        $this->withHeaders($this->headers(11))
            ->postJson('/api/delivery/entregadores', ['nome' => 'Sem contato'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp');

        $driver = $this->withHeaders($this->headers(11))
            ->postJson('/api/delivery/entregadores', [
                'nome' => 'Carlos Entregador',
                'whatsapp' => '91999999999',
                'moto_modelo' => str_repeat('M', 120),
                'moto_cor' => str_repeat('C', 64),
                'moto_placa' => 'QDA1A23',
                'ordem' => 99999,
            ])
            ->assertCreated()
            ->assertJsonPath('moto_cor', str_repeat('C', 64))
            ->json();

        $this->withHeaders($this->headers(12))
            ->getJson('/api/delivery/entregadores/'.$driver['id'])
            ->assertForbidden();

        $this->withHeaders($this->headers(12))
            ->getJson('/api/delivery/entregadores')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->withHeaders($this->headers(11))
            ->putJson('/api/delivery/entregadores/'.$driver['id'], ['ordem' => 100000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ordem');
    }

    public function test_foto_base64_pode_ser_substituida_removida_e_eh_excluida_com_registro(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $driver = $this->withHeaders($this->headers(11))
            ->postJson('/api/delivery/entregadores', [
                'nome' => 'Ana Foto', 'whatsapp' => '91988888888', 'foto_base64' => $png,
            ])
            ->assertCreated()
            ->json();

        $this->assertNotEmpty($driver['foto_path']);
        $this->assertNotNull($driver['foto_url']);
        $this->assertStringContainsString('/'.$driver['foto_path'], $driver['foto_url']);
        $this->assertStringStartsWith('uploads/delivery/entregadores/1/', $driver['foto_path']);
        $this->assertFileExists(public_path($driver['foto_path']));
        $firstPath = $driver['foto_path'];

        $updated = $this->withHeaders($this->headers(11))
            ->putJson('/api/delivery/entregadores/'.$driver['id'], ['foto_base64' => $png])
            ->assertOk()
            ->json();

        $this->assertNotSame($firstPath, $updated['foto_path']);
        $this->assertFileDoesNotExist(public_path($firstPath));
        $this->assertFileExists(public_path($updated['foto_path']));

        $removedPath = $updated['foto_path'];
        $this->withHeaders($this->headers(11))
            ->putJson('/api/delivery/entregadores/'.$driver['id'], ['remover_foto' => true])
            ->assertOk()
            ->assertJsonPath('foto_path', null)
            ->assertJsonPath('foto_url', null);
        $this->assertFileDoesNotExist(public_path($removedPath));

        $withPhotoAgain = $this->withHeaders($this->headers(11))
            ->putJson('/api/delivery/entregadores/'.$driver['id'], ['foto_base64' => $png])
            ->assertOk()
            ->json();
        $this->withHeaders($this->headers(11))
            ->deleteJson('/api/delivery/entregadores/'.$driver['id'])
            ->assertOk();
        $this->assertFileDoesNotExist(public_path($withPhotoAgain['foto_path']));
    }

    public function test_migration_adiciona_cor_da_moto(): void
    {
        $this->assertTrue(Schema::hasColumn('dlv_entregadores', 'moto_cor'));
    }

    private function headers(int $userId): array
    {
        return ['X-Usuario-Id' => (string) $userId];
    }

    private function dropSchema(): void
    {
        foreach ([
            'dlv_pedido_historico', 'dlv_pedido_itens', 'dlv_pedidos', 'dlv_entregadores',
            'dlv_frete_faixas_cep', 'dlv_produto_ingredientes', 'dlv_produto_adicional',
            'dlv_adicionais', 'dlv_produtos', 'dlv_categorias', 'dlv_loja_config', 'usuarios',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
