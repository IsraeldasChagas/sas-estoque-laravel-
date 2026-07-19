<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryFreteVitrineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! collect(Route::getRoutes())->contains(fn ($route) => ltrim($route->uri(), '/') === 'loja/{slug}/frete-resumo')) {
            require base_path('routes/web.php');
        }

        Schema::dropIfExists('dlv_frete_faixas_cep');
        Schema::dropIfExists('dlv_loja_config');

        (require database_path('migrations/2026_07_17_150000_create_delivery_tables.php'))->up();
        (require database_path('migrations/2026_07_19_120000_add_vendafacil_frete_fields_to_dlv_loja_config.php'))->up();

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => 1,
            'slug' => 'frete-loja',
            'ativo' => 1,
            'aberta' => 1,
            'frete_modo' => 'padrao_unico',
            'frete_taxa_fixa' => 12.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_frete_resumo_retorna_taxa_fixa(): void
    {
        $this->postJson('/loja/frete-loja/frete-resumo', [
            'cep' => '66010-020',
            'subtotal' => 50,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('incomplete', false)
            ->assertJsonPath('taxa', 12.5)
            ->assertJsonPath('entrega_bloqueada', false);
    }

    public function test_frete_resumo_cep_incompleto(): void
    {
        $this->postJson('/loja/frete-loja/frete-resumo', ['cep' => '6601'])
            ->assertOk()
            ->assertJsonPath('incomplete', true);
    }

    public function test_frete_resumo_faixas_cep(): void
    {
        DB::table('dlv_loja_config')->where('slug', 'frete-loja')->update(['frete_modo' => 'faixas_cep']);
        DB::table('dlv_frete_faixas_cep')->insert([
            'unidade_id' => 1,
            'cep_inicio' => '66000000',
            'cep_fim' => '66099999',
            'taxa' => 18,
            'label' => 'Centro',
            'ativo' => 1,
            'ordem' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/loja/frete-loja/frete-resumo', ['cep' => '66010-020'])
            ->assertOk()
            ->assertJsonPath('taxa', 18)
            ->assertJsonPath('rotulo', 'Centro');
    }

    public function test_calcular_entrega_rejeita_loja_sem_osrm(): void
    {
        $this->postJson('/api/calcular-entrega', [
            'slug' => 'frete-loja',
            'cep' => '66010020',
        ])->assertStatus(422);
    }
}
