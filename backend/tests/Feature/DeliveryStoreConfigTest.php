<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryStoreConfigTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $hasRoutes = collect(Route::getRoutes())->contains(
            fn ($route) => ltrim($route->uri(), '/') === 'api/delivery/vitrine'
        );
        if (! $hasRoutes) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
        }

        foreach ($this->tables() as $table) {
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

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_17_160000_add_estoque_to_delivery_products.php'))->up();

        DB::table('usuarios')->insert([
            'id' => 1,
            'nome' => 'Admin',
            'perfil' => 'ADMIN',
            'ativo' => true,
            'unidade_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    /** @return list<string> */
    private function tables(): array
    {
        return [
            'dlv_pedido_historico', 'dlv_pedido_itens', 'dlv_pedidos', 'dlv_entregadores',
            'dlv_frete_faixas_cep', 'dlv_produto_ingredientes', 'dlv_produto_adicional',
            'dlv_adicionais', 'dlv_produtos', 'dlv_categorias', 'dlv_loja_config',
            'stock_lotes', 'produtos', 'usuarios',
        ];
    }

    private function headers(): array
    {
        return ['X-Usuario-Id' => '1'];
    }

    public function test_catalogo_administrativo_inclui_ativos_ocultos_e_publico_filtra(): void
    {
        DB::table('dlv_categorias')->insert([
            'id' => 10, 'unidade_id' => 1, 'nome' => 'Lanches', 'ordem' => 1, 'ativo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('dlv_produtos')->insert([
            [
                'unidade_id' => 1, 'categoria_id' => 10, 'sku' => 'VIS-1', 'nome' => 'Publicado',
                'preco' => 10, 'estoque' => 2, 'ativo' => true, 'visivel_loja' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'unidade_id' => 1, 'categoria_id' => 10, 'sku' => 'HID-1', 'nome' => 'Oculto',
                'preco' => 12, 'estoque' => 0, 'ativo' => true, 'visivel_loja' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->withHeaders($this->headers())->getJson('/api/delivery/catalogo?unidade_id=1')
            ->assertOk()
            ->assertJsonPath('total_produtos', 2)
            ->assertJsonPath('produtos.0.nome', 'Oculto')
            ->assertJsonPath('produtos.0.disponivel', false);

        $this->withHeaders($this->headers())->getJson('/api/delivery/catalogo?unidade_id=1&somente_publicados=1')
            ->assertOk()
            ->assertJsonPath('total_produtos', 1)
            ->assertJsonPath('produtos.0.nome', 'Publicado');
    }

    public function test_vitrine_salva_substitui_e_remove_imagens_da_unidade(): void
    {
        $first = $this->withHeaders($this->headers())->putJson('/api/delivery/vitrine?unidade_id=1', [
            'unidade_id' => 1,
            'nome_loja' => 'Sabor Paraense',
            'slug' => 'sabor-paraense',
            'logo_base64' => self::PNG,
            'banner_base64' => self::PNG,
            'cor_primaria' => '#15803d',
            'ativo' => true,
            'aberta' => true,
        ])->assertOk()
            ->assertJsonPath('logo_url', fn ($value) => str_starts_with($value, '/uploads/delivery/lojas/1/logo-'))
            ->assertJsonPath('banner_url', fn ($value) => str_starts_with($value, '/uploads/delivery/lojas/1/banner-'))
            ->assertJsonPath('public_route_available', false)
            ->json();

        $oldLogo = $first['logo_path'];
        $banner = $first['banner_path'];
        $this->assertFileExists(public_path($oldLogo));
        $this->assertFileExists(public_path($banner));

        $second = $this->withHeaders($this->headers())->putJson('/api/delivery/vitrine?unidade_id=1', [
            'unidade_id' => 1,
            'logo_base64' => self::PNG,
            'banner_clear' => true,
        ])->assertOk()->json();

        $this->assertNotSame($oldLogo, $second['logo_path']);
        $this->assertFileDoesNotExist(public_path($oldLogo));
        $this->assertFileExists(public_path($second['logo_path']));
        $this->assertNull($second['banner_path']);
        $this->assertNull($second['banner_url']);
        $this->assertFileDoesNotExist(public_path($banner));

        @unlink(public_path($second['logo_path']));
    }

    public function test_configuracoes_persistem_campos_estruturados_existentes(): void
    {
        $this->withHeaders($this->headers())->putJson('/api/delivery/configuracoes?unidade_id=1', [
            'unidade_id' => 1,
            'aberta' => true,
            'confirmar_pedidos' => false,
            'nome_loja' => 'Loja Um',
            'pix_tipo' => 'cnpj',
            'pix_chave' => '12.345.678/0001-90',
            'pix_beneficiario' => 'Loja Um Ltda',
            'frete_modo' => 'cep_band',
            'frete_taxa_fixa' => 8.5,
            'frete_gratis_acima' => 100,
            'frete_acrescimo_chuva_percent' => 15,
            'frete_chuva_ativa' => true,
            'permite_retirada' => true,
            'formas_pagamento' => 'pix,cartao',
        ])->assertOk()
            ->assertJsonPath('aberta', true)
            ->assertJsonPath('confirmar_pedidos', false)
            ->assertJsonPath('pix_tipo', 'cnpj')
            ->assertJsonPath('frete_modo', 'cep_band')
            ->assertJsonPath('formas_pagamento', 'pix,cartao');
    }
}
