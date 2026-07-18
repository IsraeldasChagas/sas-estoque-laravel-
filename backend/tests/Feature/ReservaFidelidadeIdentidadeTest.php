<?php

namespace Tests\Feature;

use App\Models\ReservaMesa;
use App\Services\Fidelidade\FidelidadeCodigoService;
use App\Services\Fidelidade\ReservaFidelidadeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservaFidelidadeIdentidadeTest extends TestCase
{
    private int $unidadeId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas',
            'reservas_mesas', 'mesas', 'unidades',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });

        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome')->nullable();
            $table->string('status')->default('livre');
            $table->integer('capacidade')->default(4);
            $table->timestamps();
        });

        Schema::create('reservas_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('mesa_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('nome_cliente')->nullable();
            $table->string('telefone_cliente')->nullable();
            $table->date('data_reserva')->nullable();
            $table->time('hora_reserva')->nullable();
            $table->integer('qtd_pessoas')->default(2);
            $table->string('status')->default('pendente');
            $table->boolean('participa_fidelidade')->default(false);
            $table->string('fidelidade_nome', 160)->nullable();
            $table->string('fidelidade_cpf', 11)->nullable();
            $table->string('fidelidade_email', 160)->nullable();
            $table->boolean('conta_paga')->default(false);
            $table->decimal('valor_conta', 10, 2)->nullable();
            $table->timestamp('conta_paga_em')->nullable();
            $table->json('pagamentos_conta')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_07_17_140000_create_fidelidade_tables.php');
        $migration->up();

        $this->unidadeId = (int) DB::table('unidades')->insertGetId([
            'nome' => 'Unidade Teste',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fid_programas')->insert([
            'unidade_id' => $this->unidadeId,
            'ativo' => 1,
            'nome_exibicao' => 'Cartão',
            'modo' => 'selos',
            'pedidos_meta' => 10,
            'pontos_por_selo' => 1,
            'tipo_recompensa_padrao' => 'produto',
            'permite_ajuste_manual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas',
            'reservas_mesas', 'mesas', 'unidades',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_bloqueia_cpf_em_outro_telefone(): void
    {
        DB::table('fid_contas')->insert([
            'unidade_id' => $this->unidadeId,
            'telefone_normalizado' => '69911111111',
            'cpf_normalizado' => '52998224725',
            'email' => 'outro@email.com',
            'nome' => 'Outro Cliente',
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 0,
            'saldo_pontos' => 0,
            'total_resgates' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reserva = ReservaMesa::create([
            'unidade_id' => $this->unidadeId,
            'nome_cliente' => 'Israel',
            'telefone_cliente' => '69984639070',
            'data_reserva' => now()->toDateString(),
            'hora_reserva' => '19:00',
            'qtd_pessoas' => 2,
            'status' => 'cliente_chegou',
            'participa_fidelidade' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ReservaFidelidadeService::class)->salvarDadosFidelidade(
            $reserva,
            'Israel das Chagas',
            '529.982.247-25',
            'israel@gruposaborparaense.com.br'
        );
    }

    public function test_salva_dados_e_cria_cartao_com_identidade(): void
    {
        $reserva = ReservaMesa::create([
            'unidade_id' => $this->unidadeId,
            'nome_cliente' => 'Israel',
            'telefone_cliente' => '69984639070',
            'data_reserva' => now()->toDateString(),
            'hora_reserva' => '19:00',
            'qtd_pessoas' => 2,
            'status' => 'cliente_chegou',
            'participa_fidelidade' => true,
        ]);

        app(ReservaFidelidadeService::class)->salvarDadosFidelidade(
            $reserva,
            'Israel das Chagas',
            '529.982.247-25',
            'israel@gruposaborparaense.com.br'
        );

        $reserva->refresh();
        $this->assertSame('Israel das Chagas', $reserva->fidelidade_nome);
        $this->assertSame('52998224725', $reserva->fidelidade_cpf);

        $conta = app(ReservaFidelidadeService::class)->garantirConta($reserva, null);
        $this->assertSame('52998224725', $conta->cpf_normalizado);
        $this->assertSame('israel@gruposaborparaense.com.br', $conta->email);
    }
}
