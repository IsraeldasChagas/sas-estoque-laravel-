<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryCatalogAdminTest extends TestCase
{
    private const UNIT_A = 987601;

    private const UNIT_B = 987602;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerDeliveryRoutes();
        $this->createSchema();
        $this->seedUsers();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('uploads/delivery/adicionais/'.self::UNIT_A));
        File::deleteDirectory(public_path('uploads/delivery/adicionais/'.self::UNIT_B));

        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_categories_have_counts_case_insensitive_unique_names_and_safe_deletion(): void
    {
        $category = $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => self::UNIT_A,
            'nome' => '  Bebidas  ',
            'ordem' => 12,
            'ativo' => false,
        ])->assertCreated()->json();

        $this->assertSame('Bebidas', $category['nome']);

        $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => self::UNIT_A,
            'nome' => 'bebidas',
        ])->assertUnprocessable()->assertJsonValidationErrors('nome');

        $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => self::UNIT_B,
            'nome' => 'BEBIDAS',
        ])->assertCreated();

        $longName = str_repeat('C', 255);
        $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => self::UNIT_A,
            'nome' => $longName,
            'ordem' => 65535,
        ])->assertCreated();

        $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => self::UNIT_A,
            'nome' => str_repeat('C', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors('nome');

        $this->withHeaders($this->headers())->postJson('/api/delivery/categorias', [
            'unidade_id' => self::UNIT_A,
            'nome' => 'Ordem inválida',
            'ordem' => 65536,
        ])->assertUnprocessable()->assertJsonValidationErrors('ordem');

        $productId = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => self::UNIT_A,
            'categoria_id' => $category['id'],
            'nome' => 'Água',
            'preco' => 4,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/categorias?unidade_id='.self::UNIT_A)
            ->assertOk()
            ->assertJsonPath('items.0.nome', 'Bebidas')
            ->assertJsonPath('items.0.product_count', 1);

        $this->withHeaders($this->headers())
            ->deleteJson('/api/delivery/categorias/'.$category['id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categoria')
            ->assertJsonFragment(['categoria' => ['Esta categoria está vinculada a 1 produto(s). Altere a categoria dos produtos antes de excluir.']]);

        $this->assertDatabaseHas('dlv_produtos', [
            'id' => $productId,
            'categoria_id' => $category['id'],
        ]);
        $this->assertDatabaseHas('dlv_categorias', ['id' => $category['id']]);
    }

    public function test_additionals_validate_fields_count_products_and_force_removal_price_to_zero(): void
    {
        $additional = $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => self::UNIT_A,
            'nome' => 'Sem cebola',
            'tipo' => 'retirar',
            'preco' => 99.9,
            'ordem' => 9999,
            'ativo' => false,
        ])->assertCreated()
            ->assertJsonPath('preco', 0)
            ->json();

        $productId = DB::table('dlv_produtos')->insertGetId([
            'unidade_id' => self::UNIT_A,
            'nome' => 'Hambúrguer',
            'preco' => 20,
        ]);
        DB::table('dlv_produto_adicional')->insert([
            'produto_id' => $productId,
            'adicional_id' => $additional['id'],
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/api/delivery/adicionais?unidade_id='.self::UNIT_A)
            ->assertOk()
            ->assertJsonPath('items.0.product_count', 1)
            ->assertJsonPath('items.0.foto_url', null);

        $this->withHeaders($this->headers())->putJson('/api/delivery/adicionais/'.$additional['id'], [
            'tipo' => 'retirar',
            'preco' => 50,
        ])->assertOk()->assertJsonPath('preco', 0);

        foreach ([
            ['nome' => 'Campos obrigatórios', 'ordem' => 0],
            ['nome' => str_repeat('A', 121), 'tipo' => 'acrescentar', 'preco' => 1, 'ordem' => 0],
            ['nome' => 'Tipo', 'tipo' => 'outro', 'preco' => 1, 'ordem' => 0],
            ['nome' => 'Preço', 'tipo' => 'acrescentar', 'preco' => -1, 'ordem' => 0],
            ['nome' => 'Ordem', 'tipo' => 'acrescentar', 'preco' => 1, 'ordem' => 10000],
        ] as $payload) {
            $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
                'unidade_id' => self::UNIT_A,
                ...$payload,
            ])->assertUnprocessable();
        }

        $this->withHeaders($this->headers(3))
            ->getJson('/api/delivery/adicionais/'.$additional['id'])
            ->assertForbidden();
    }

    public function test_additional_photo_upload_replacement_removal_deletion_and_traversal_rejection(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $additional = $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => self::UNIT_A,
            'nome' => 'Bacon',
            'tipo' => 'acrescentar',
            'preco' => 4,
            'ordem' => 1,
            'foto_base64' => $png,
        ])->assertCreated()->json();

        $this->assertStringStartsWith('uploads/delivery/adicionais/'.self::UNIT_A.'/', $additional['foto_path']);
        $this->assertSame('/'.$additional['foto_path'], $additional['foto_url']);
        $this->assertFileExists(public_path($additional['foto_path']));

        $oldPath = $additional['foto_path'];
        $updated = $this->withHeaders($this->headers())->putJson('/api/delivery/adicionais/'.$additional['id'], [
            'foto_base64' => $png,
        ])->assertOk()->json();

        $this->assertNotSame($oldPath, $updated['foto_path']);
        $this->assertFileDoesNotExist(public_path($oldPath));
        $this->assertFileExists(public_path($updated['foto_path']));

        $newPath = $updated['foto_path'];
        $this->withHeaders($this->headers())->putJson('/api/delivery/adicionais/'.$additional['id'], [
            'remover_foto' => true,
        ])->assertOk()
            ->assertJsonPath('foto_path', null)
            ->assertJsonPath('foto_url', null);
        $this->assertFileDoesNotExist(public_path($newPath));

        $withPhotoAgain = $this->withHeaders($this->headers())->putJson('/api/delivery/adicionais/'.$additional['id'], [
            'foto_base64' => $png,
        ])->assertOk()->json();
        $this->assertFileExists(public_path($withPhotoAgain['foto_path']));

        $this->withHeaders($this->headers())
            ->deleteJson('/api/delivery/adicionais/'.$additional['id'])
            ->assertOk();
        $this->assertFileDoesNotExist(public_path($withPhotoAgain['foto_path']));

        $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => self::UNIT_A,
            'nome' => 'Traversal',
            'tipo' => 'acrescentar',
            'preco' => 1,
            'ordem' => 0,
            'foto_path' => '../../.env',
        ])->assertUnprocessable()->assertJsonValidationErrors('foto_path');

        $oversized = 'data:image/png;base64,'.base64_encode(str_repeat('x', (2 * 1024 * 1024) + 1));
        $this->withHeaders($this->headers())->postJson('/api/delivery/adicionais', [
            'unidade_id' => self::UNIT_A,
            'nome' => 'Grande',
            'tipo' => 'acrescentar',
            'preco' => 1,
            'ordem' => 0,
            'foto_base64' => $oversized,
        ])->assertUnprocessable()->assertJsonValidationErrors('foto_base64');
    }

    private function registerDeliveryRoutes(): void
    {
        $exists = collect(Route::getRoutes())->contains(
            fn ($route) => $route->uri() === 'api/delivery/categorias'
        );

        if (! $exists) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/delivery_routes.php'));
        }
    }

    private function createSchema(): void
    {
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

        $baseMigration = require database_path('migrations/2026_07_17_150000_create_delivery_tables.php');
        $baseMigration->up();
        $improvementMigration = require database_path('migrations/2026_07_17_170000_improve_delivery_categories_additionals.php');
        $improvementMigration->up();
    }

    private function seedUsers(): void
    {
        DB::table('usuarios')->insert([
            [
                'id' => 1,
                'nome' => 'Admin',
                'perfil' => 'ADMIN',
                'ativo' => 1,
                'unidade_id' => self::UNIT_A,
                'permissoes_menu' => null,
            ],
            [
                'id' => 2,
                'nome' => 'Operador A',
                'perfil' => 'OPERADOR',
                'ativo' => 1,
                'unidade_id' => self::UNIT_A,
                'permissoes_menu' => json_encode(['deliveryCategorias', 'deliveryAdicionais']),
            ],
            [
                'id' => 3,
                'nome' => 'Operador B',
                'perfil' => 'OPERADOR',
                'ativo' => 1,
                'unidade_id' => self::UNIT_B,
                'permissoes_menu' => json_encode(['deliveryCategorias', 'deliveryAdicionais']),
            ],
        ]);
    }

    private function headers(int $userId = 1): array
    {
        return ['X-Usuario-Id' => (string) $userId];
    }

    private function tables(): array
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
            'usuarios',
        ];
    }
}
