<?php

namespace Tests\Feature;

use App\Services\Fidelidade\FidelidadeCodigoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryFidelidadeVitrineRecompensaTest extends TestCase
{
    private int $unidadeId;

    private string $slug = 'loja-vitrine-rec';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas',
            'dlv_loja_config', 'unidades',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });

        Schema::create('dlv_loja_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('slug')->unique();
            $table->string('nome_loja')->nullable();
            $table->boolean('ativo')->default(1);
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_07_17_140000_create_fidelidade_tables.php');
        $migration->up();

        $pctMigration = require database_path('migrations/2026_07_18_220000_add_desconto_percentual_to_fid_programas.php');
        $pctMigration->up();

        $this->unidadeId = (int) DB::table('unidades')->insertGetId([
            'nome' => 'Unidade Vitrine',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => $this->unidadeId,
            'slug' => $this->slug,
            'nome_loja' => 'Loja Vitrine',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_vitrine_produto_mostra_descricao_configurada(): void
    {
        $this->seedPrograma('produto', ['texto_recompensa' => '1 caldo de pato']);

        $this->aceitarLgpd();

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Como funciona')
            ->assertSee('Recompensa')
            ->assertSee('1 caldo de pato')
            ->assertDontSee('Desconto percentual');
    }

    public function test_vitrine_percentual_mostra_somente_bloco_percentual(): void
    {
        $this->seedPrograma('desconto_percentual', [
            'desconto_percentual' => 15,
            'texto_recompensa' => 'Texto de produto ignorado',
        ]);

        $this->aceitarLgpd();

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Desconto percentual')
            ->assertSee('15%')
            ->assertDontSee('Texto de produto ignorado')
            ->assertDontSee('Desconto na conta');
    }

    private function seedPrograma(string $tipo, array $extra = []): void
    {
        DB::table('fid_programas')->insert(array_merge([
            'unidade_id' => $this->unidadeId,
            'ativo' => 1,
            'nome_exibicao' => 'Cartão teste',
            'modo' => 'selos',
            'pedidos_meta' => 10,
            'pontos_por_selo' => 1,
            'tipo_recompensa_padrao' => $tipo,
            'permite_ajuste_manual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));

        DB::table('fid_contas')->insert([
            'unidade_id' => $this->unidadeId,
            'telefone_normalizado' => '69999998888',
            'nome' => 'Cliente',
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 2,
            'saldo_pontos' => 2,
            'total_resgates' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function aceitarLgpd(): void
    {
        $this->from('/loja/'.$this->slug.'/fidelidade')
            ->post('/loja/'.$this->slug.'/fidelidade/aceitar-lgpd', ['lgpd_autorizo' => '1'])
            ->assertRedirect('/loja/'.$this->slug.'/fidelidade');
    }
}
