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

    public function test_linhas_produto_usam_texto_recompensa(): void
    {
        $programa = (object) [
            'tipo_recompensa_padrao' => 'produto',
            'pedidos_meta' => 10,
            'texto_recompensa' => '1 tacacá especial',
        ];

        $linhas = $this->svc->linhasRecompensa($programa);
        $this->assertCount(1, $linhas);
        $this->assertStringContainsString('1 tacacá especial', $linhas[0]);
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

        $linhas = $this->svc->linhasComoFunciona($programa, 'Unidade Centro');
        $this->assertTrue(collect($linhas)->contains(fn ($l) => str_contains($l, 'cliente que fez a reserva')));
        $this->assertTrue(collect($linhas)->contains(fn ($l) => str_contains($l, 'Sobremesa')));
    }
}
