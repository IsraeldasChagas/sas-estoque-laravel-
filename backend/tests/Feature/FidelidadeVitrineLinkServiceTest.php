<?php

namespace Tests\Feature;

use App\Models\ReservaMesa;
use App\Services\Fidelidade\FidelidadeVitrineLinkService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Tests\TestCase;

class FidelidadeVitrineLinkServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('dlv_loja_config');
        Schema::dropIfExists('reservas_mesas');
        Schema::dropIfExists('unidades');

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });

        Schema::create('reservas_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome_cliente')->nullable();
            $table->string('telefone_cliente')->nullable();
            $table->string('fidelidade_nome')->nullable();
            $table->boolean('participa_fidelidade')->default(false);
            $table->timestamps();
        });

        Schema::create('dlv_loja_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('unidade_fidelidade_id')->nullable();
            $table->string('slug')->unique();
            $table->string('nome_loja')->nullable();
            $table->boolean('ativo')->default(1);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dlv_loja_config');
        Schema::dropIfExists('reservas_mesas');
        Schema::dropIfExists('unidades');
        parent::tearDown();
    }

    public function test_gera_link_pela_unidade_fidelidade_vinculada(): void
    {
        $unidadeReserva = (int) DB::table('unidades')->insertGetId(['nome' => 'Reserva', 'created_at' => now(), 'updated_at' => now()]);
        $unidadeVitrine = (int) DB::table('unidades')->insertGetId(['nome' => 'Delivery', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('dlv_loja_config')->insert([
            'unidade_id' => $unidadeVitrine,
            'unidade_fidelidade_id' => $unidadeReserva,
            'slug' => 'sabor-paraense-2',
            'nome_loja' => 'SaborParaense 2',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reserva = ReservaMesa::create([
            'unidade_id' => $unidadeReserva,
            'nome_cliente' => 'Israel',
            'telefone_cliente' => '69984639070',
            'participa_fidelidade' => true,
        ]);

        $request = Request::create('https://api.gruposaborparaense.com.br/api/reservas-mesas/1/fidelidade', 'GET');
        $info = app(FidelidadeVitrineLinkService::class)->paraReserva($reserva, $request);

        $this->assertSame('https://api.gruposaborparaense.com.br/loja/sabor-paraense-2/fidelidade', $info['url']);
        $this->assertStringContainsString('wa.me/5569984639070', (string) $info['whatsapp_url']);
        $this->assertStringContainsString('sabor-paraense-2/fidelidade', (string) $info['mensagem_whatsapp']);
    }
}
