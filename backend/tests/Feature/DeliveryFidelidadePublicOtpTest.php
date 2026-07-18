<?php

namespace Tests\Feature;

use App\Services\Fidelidade\FidelidadeCodigoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryFidelidadePublicOtpTest extends TestCase
{
    private int $unidadeId;

    private string $slug = 'loja-teste-fid';

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

        $this->unidadeId = (int) DB::table('unidades')->insertGetId([
            'nome' => 'Unidade Fid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => $this->unidadeId,
            'slug' => $this->slug,
            'nome_loja' => 'Loja Fid',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fid_programas')->insert([
            'unidade_id' => $this->unidadeId,
            'ativo' => 1,
            'nome_exibicao' => 'Cartão teste',
            'modo' => 'selos',
            'pedidos_meta' => 10,
            'pontos_por_selo' => 1,
            'tipo_recompensa_padrao' => 'produto',
            'permite_ajuste_manual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fid_contas')->insert([
            'unidade_id' => $this->unidadeId,
            'telefone_normalizado' => '69999998888',
            'email' => null,
            'nome' => 'Cliente Teste',
            'codigo_fidelidade' => FidelidadeCodigoService::gerar(),
            'status' => 'ativo',
            'saldo_selos' => 4,
            'saldo_pontos' => 4,
            'total_resgates' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            'fid_resgates', 'fid_recompensas', 'fid_ledger', 'fid_contas', 'fid_programas',
            'dlv_loja_config', 'unidades',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_nao_mostra_saldo_sem_otp(): void
    {
        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Solicitar código')
            ->assertDontSee('Seu progresso');
    }

    public function test_otp_wa_me_e_verificacao_mostra_selos(): void
    {
        config(['services.fidelidade_otp.email_fallback' => false, 'services.fidelidade_otp.wa_me_fallback' => true]);

        $this->post('/loja/'.$this->slug.'/fidelidade/solicitar-codigo', [
            'telefone' => '(69) 99999-8888',
        ])->assertRedirect('/loja/'.$this->slug.'/fidelidade');

        $codigo = Cache::get('sas_fid_otp:'.$this->unidadeId.':69999998888');
        $this->assertIsString($codigo);
        $this->assertSame(6, strlen($codigo));

        $this->post('/loja/'.$this->slug.'/fidelidade/verificar-codigo', [
            'codigo' => $codigo,
        ])->assertRedirect('/loja/'.$this->slug.'/fidelidade');

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertOk()
            ->assertSee('Seu progresso')
            ->assertSee('***8888')
            ->assertSee('4');
    }

    public function test_codigo_errado_bloqueia_acesso(): void
    {
        config(['services.fidelidade_otp.email_fallback' => false, 'services.fidelidade_otp.wa_me_fallback' => true]);

        $this->post('/loja/'.$this->slug.'/fidelidade/solicitar-codigo', [
            'telefone' => '69999998888',
        ]);

        $this->from('/loja/'.$this->slug.'/fidelidade')
            ->post('/loja/'.$this->slug.'/fidelidade/verificar-codigo', ['codigo' => '000000'])
            ->assertRedirect('/loja/'.$this->slug.'/fidelidade')
            ->assertSessionHasErrors('codigo');

        $this->get('/loja/'.$this->slug.'/fidelidade')
            ->assertDontSee('Seu progresso');
    }
}
