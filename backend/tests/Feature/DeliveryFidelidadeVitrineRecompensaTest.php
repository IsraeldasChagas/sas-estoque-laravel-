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

        $catalogoMigration = require database_path('migrations/2026_07_19_180000_add_catalogo_consulta_to_fid_programas.php');
        $catalogoMigration->up();

        $resgateCatalogoMigration = require database_path('migrations/2026_07_19_200000_add_catalogo_escolhas_json_to_fid_resgates.php');
        $resgateCatalogoMigration->up();

        $unidadeFidMigration = require database_path('migrations/2026_07_18_180000_add_unidade_fidelidade_id_to_dlv_loja_config.php');
        $unidadeFidMigration->up();

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
        $this->seedPrograma('catalogo_consulta', [
            'catalogo_qtd_escolhas' => 1,
            'catalogo_produtos_json' => json_encode([
                ['id' => 1, 'nome' => '1 caldo de pato'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->aceitarLgpd();

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Como funciona')
            ->assertSee('Catálogo (consulta)')
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

    public function test_vitrine_catalogo_consulta_mostra_escolha_e_produtos(): void
    {
        $this->seedPrograma('catalogo_consulta', [
            'catalogo_qtd_escolhas' => 3,
            'catalogo_produtos_json' => json_encode([
                ['id' => 1, 'nome' => 'Tacacá'],
                ['id' => 2, 'nome' => 'Açaí 500ml'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->aceitarLgpd();

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Catálogo (consulta)')
            ->assertSee('opção(ões) na vitrine')
            ->assertSee('no resgate, escolha')
            ->assertSee('3')
            ->assertSee('Tacacá')
            ->assertSee('Açaí 500ml')
            ->assertSee('Pode repetir o mesmo produto');
    }

    public function test_vitrine_usa_catalogo_salvo_na_unidade_delivery_quando_fidelidade_esta_vazia(): void
    {
        $unidadeFid = $this->unidadeId + 100;
        DB::table('unidades')->insert([
            'id' => $unidadeFid,
            'nome' => 'Unidade Fidelidade Vitrine',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dlv_loja_config')->where('slug', $this->slug)->update([
            'unidade_fidelidade_id' => $unidadeFid,
        ]);

        DB::table('fid_programas')->insert([
            [
                'unidade_id' => $unidadeFid,
                'ativo' => 1,
                'nome_exibicao' => 'Cartão fidelidade reserva',
                'modo' => 'selos',
                'pedidos_meta' => 10,
                'pontos_por_selo' => 1,
                'tipo_recompensa_padrao' => 'catalogo_consulta',
                'catalogo_qtd_escolhas' => 1,
                'catalogo_produtos_json' => null,
                'permite_ajuste_manual' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'unidade_id' => $this->unidadeId,
                'ativo' => 1,
                'nome_exibicao' => 'Cartão delivery',
                'modo' => 'selos',
                'pedidos_meta' => 10,
                'pontos_por_selo' => 1,
                'tipo_recompensa_padrao' => 'catalogo_consulta',
                'catalogo_qtd_escolhas' => 2,
                'catalogo_produtos_json' => json_encode([
                    ['id' => 10, 'nome' => 'Maracujá 300 ml'],
                    ['id' => 11, 'nome' => 'Menu Degustação Pavulagem 3 Opções'],
                ], JSON_UNESCAPED_UNICODE),
                'permite_ajuste_manual' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->aceitarLgpd();

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Maracujá 300 ml')
            ->assertSee('Menu Degustação Pavulagem 3 Opções')
            ->assertSee('no resgate, escolha')
            ->assertSee('2');
    }

    public function test_vitrine_resgate_catalogo_escolhe_uma_de_tres_opcoes(): void
    {
        $this->seedPrograma('catalogo_consulta', [
            'pedidos_meta' => 10,
            'catalogo_qtd_escolhas' => 1,
            'catalogo_produtos_json' => json_encode([
                ['id' => 1, 'nome' => 'Maracujá 300 ml'],
                ['id' => 2, 'nome' => 'Menu Degustação Pavulagem 3 Opções'],
                ['id' => 3, 'nome' => 'Monster'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $contaId = (int) DB::table('fid_contas')->where('telefone_normalizado', '69999998888')->value('id');
        DB::table('fid_contas')->where('id', $contaId)->update(['saldo_selos' => 10, 'saldo_pontos' => 10]);

        $this->aceitarLgpd();
        session([
            'sas_fid_acesso' => [
                'unidade_id' => $this->unidadeId,
                'unidade_fidelidade_id' => $this->unidadeId,
                'conta_id' => $contaId,
                'tel_norm' => '69999998888',
                'exp' => now()->addHour()->timestamp,
            ],
        ]);

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Resgatar recompensa')
            ->assertSee('Confirmar resgate');

        $this->post('/loja/'.$this->slug.'/fidelidade/resgatar', [
            'catalogo_produto_id' => 3,
        ])->assertRedirect('/loja/'.$this->slug.'/fidelidade');

        $this->assertDatabaseHas('fid_resgates', [
            'conta_id' => $contaId,
            'titulo_snapshot' => 'Monster',
        ]);
        $this->assertSame(0, (int) DB::table('fid_contas')->where('id', $contaId)->value('saldo_selos'));
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
