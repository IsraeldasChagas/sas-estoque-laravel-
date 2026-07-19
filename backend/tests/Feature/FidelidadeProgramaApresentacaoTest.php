<?php

namespace Tests\Feature;

use App\Services\Fidelidade\FidelidadeProgramaApresentacaoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FidelidadeProgramaApresentacaoTest extends TestCase
{
    private FidelidadeProgramaApresentacaoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(FidelidadeProgramaApresentacaoService::class);

        foreach (['fid_ledger', 'fid_contas', 'fid_programas', 'reservas_mesas', 'unidades'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });

        Schema::create('reservas_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->decimal('valor_conta', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('fid_contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('telefone_normalizado', 20);
            $table->string('status', 20)->default('ativo');
            $table->integer('saldo_selos')->default(0);
            $table->timestamps();
        });

        Schema::create('fid_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('conta_id');
            $table->string('tipo', 30);
            $table->integer('delta_selos')->default(0);
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->unsignedBigInteger('reverso_de_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_linhas_catalogo_consulta_listam_produtos_e_quantidade(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'catalogo_consulta',
            'pedidos_meta' => 10,
            'catalogo_qtd_escolhas' => 2,
            'catalogo_produtos_json' => json_encode([
                ['id' => 1, 'nome' => 'Tacacá'],
                ['id' => 2, 'nome' => 'Açaí'],
            ]),
        ];

        $linhas = $this->svc->linhasRecompensa($programa);
        $this->assertGreaterThanOrEqual(3, count($linhas));
        $this->assertStringContainsString('Catálogo (consulta)', $linhas[0]);
        $this->assertStringContainsString('no resgate, escolha 2 item(ns)', $linhas[0]);
        $this->assertSame('Tacacá', $linhas[1]);
        $this->assertSame('Açaí', $linhas[2]);
        $this->assertStringContainsString('repetir o mesmo produto', $linhas[3]);
    }

    public function test_como_funciona_catalogo_consulta_expoe_produtos_na_vitrine(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'catalogo_consulta',
            'pedidos_meta' => 10,
            'catalogo_qtd_escolhas' => 3,
            'catalogo_produtos_json' => json_encode([
                ['id' => 1, 'nome' => 'Tacacá'],
                ['id' => 2, 'nome' => 'Açaí'],
            ]),
        ];

        $bloco = $this->svc->comoFuncionaVitrine($programa, 'Unidade Centro');
        $this->assertSame('catalogo_consulta', $bloco['tipo']);
        $this->assertSame(3, $bloco['catalogo_qtd_escolhas']);
        $this->assertCount(2, $bloco['catalogo_produtos']);
        $this->assertSame('Tacacá', $bloco['catalogo_produtos'][0]['nome']);
        $this->assertTrue(collect($bloco['recompensa_linhas'])->contains(fn ($l) => $l === 'Açaí'));
    }

    public function test_linhas_produto_legacy_nao_usam_texto_recompensa(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'produto',
            'pedidos_meta' => 10,
            'texto_recompensa' => '1 tacacá especial',
        ];

        $linhas = $this->svc->linhasRecompensa($programa);
        $this->assertGreaterThanOrEqual(1, count($linhas));
        $this->assertFalse(collect($linhas)->contains(fn ($l) => str_contains($l, '1 tacacá especial')));
    }

    public function test_linhas_percentual_explicam_gasto_acumulado(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'desconto_percentual',
            'pedidos_meta' => 10,
            'desconto_percentual' => 15,
            'base_desconto_percentual' => 'gasto_acumulado_meta',
        ];

        $linhas = $this->svc->linhasRecompensa($programa);
        $this->assertTrue(collect($linhas)->contains(fn ($l) => str_contains($l, '15%')));
        $this->assertTrue(collect($linhas)->contains(fn ($l) => str_contains($l, '10 reserva')));
    }

    public function test_gasto_acumulado_soma_contas_das_reservas_com_selo(): void
    {
        $contaId = (int) DB::table('fid_contas')->insertGetId([
            'unidade_id' => 1,
            'telefone_normalizado' => '69999998888',
            'status' => 'ativo',
            'saldo_selos' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r1 = (int) DB::table('reservas_mesas')->insertGetId(['unidade_id' => 1, 'valor_conta' => 80.00, 'created_at' => now(), 'updated_at' => now()]);
        $r2 = (int) DB::table('reservas_mesas')->insertGetId(['unidade_id' => 1, 'valor_conta' => 120.50, 'created_at' => now(), 'updated_at' => now()]);

        foreach ([$r1, $r2] as $rid) {
            DB::table('fid_ledger')->insert([
                'unidade_id' => 1,
                'conta_id' => $contaId,
                'tipo' => 'selo',
                'delta_selos' => 1,
                'referencia_tipo' => 'reserva_mesa',
                'referencia_id' => $rid,
                'created_at' => now(),
            ]);
        }

        $gasto = $this->svc->gastoAcumuladoSelos($contaId, 10);
        $this->assertSame(200.5, $gasto);
    }

    public function test_como_funciona_inclui_regra_do_cliente_da_reserva(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'brinde',
            'pedidos_meta' => 10,
            'texto_recompensa' => 'Sobremesa',
        ];

        $bloco = $this->svc->comoFuncionaVitrine($programa, 'Unidade Centro');
        $this->assertSame('brinde', $bloco['tipo']);
        $this->assertTrue(collect($bloco['regras'])->contains(fn ($l) => str_contains($l, 'cliente que fez a reserva')));
        $this->assertSame('Brinde', $bloco['recompensa_titulo']);
        $this->assertTrue(collect($bloco['recompensa_linhas'])->contains(fn ($l) => str_contains($l, 'Sobremesa')));
    }

    public function test_como_funciona_percentual_nao_mostra_texto_produto(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'desconto_percentual',
            'pedidos_meta' => 10,
            'desconto_percentual' => 12,
            'texto_recompensa' => 'Isso nao deve aparecer',
        ];

        $bloco = $this->svc->comoFuncionaVitrine($programa, 'Unidade Centro');
        $this->assertSame('desconto_percentual', $bloco['tipo']);
        $this->assertSame('Desconto percentual', $bloco['recompensa_titulo']);
        $this->assertTrue(collect($bloco['recompensa_linhas'])->contains(fn ($l) => str_contains($l, '12%')));
        $this->assertFalse(collect($bloco['recompensa_linhas'])->contains(fn ($l) => str_contains($l, 'Isso nao deve aparecer')));
    }
}
