<?php

namespace Tests\Feature;

use App\Models\ReservaMesa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservaHistoricoPagamentosTest extends TestCase
{
    private int $unidadeId;

    private int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reservas_mesas', 'mesas', 'usuarios', 'unidades'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->nullable();
            $table->string('perfil')->default('ADMIN');
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome_mesa')->nullable();
            $table->integer('numero_mesa')->nullable();
            $table->timestamps();
        });

        Schema::create('reservas_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('mesa_id')->nullable();
            $table->string('nome_cliente')->nullable();
            $table->string('telefone_cliente')->nullable();
            $table->date('data_reserva')->nullable();
            $table->time('hora_reserva')->nullable();
            $table->integer('qtd_pessoas')->default(2);
            $table->string('status')->default('finalizada');
            $table->boolean('conta_paga')->default(false);
            $table->decimal('valor_conta', 10, 2)->nullable();
            $table->timestamp('conta_paga_em')->nullable();
            $table->json('pagamentos_conta')->nullable();
            $table->timestamps();
        });

        $this->unidadeId = (int) DB::table('unidades')->insertGetId([
            'nome' => 'Unidade Teste',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->usuarioId = (int) DB::table('usuarios')->insertGetId([
            'nome' => 'Admin',
            'perfil' => 'ADMIN',
            'unidade_id' => $this->unidadeId,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['reservas_mesas', 'mesas', 'usuarios', 'unidades'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_historico_retorna_campos_de_pagamento(): void
    {
        $mesaId = (int) DB::table('mesas')->insertGetId([
            'unidade_id' => $this->unidadeId,
            'nome_mesa' => 'Mesa 1',
            'numero_mesa' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ReservaMesa::create([
            'unidade_id' => $this->unidadeId,
            'mesa_id' => $mesaId,
            'nome_cliente' => 'Maria Silva',
            'telefone_cliente' => '(69) 99999-8888',
            'data_reserva' => '2026-07-10',
            'hora_reserva' => '19:00:00',
            'qtd_pessoas' => 4,
            'status' => 'finalizada',
            'conta_paga' => true,
            'valor_conta' => 150.50,
            'conta_paga_em' => now(),
            'pagamentos_conta' => [
                ['tipo' => 'pix', 'tipo_label' => 'PIX', 'meio_nome' => 'Caixa', 'valor' => 150.50],
            ],
        ]);

        ReservaMesa::create([
            'unidade_id' => $this->unidadeId,
            'mesa_id' => $mesaId,
            'nome_cliente' => 'Maria Silva',
            'telefone_cliente' => '(69) 99999-8888',
            'data_reserva' => '2026-07-05',
            'hora_reserva' => '20:00:00',
            'qtd_pessoas' => 2,
            'status' => 'finalizada',
            'conta_paga' => false,
        ]);

        $response = $this->withHeaders(['X-Usuario-Id' => (string) $this->usuarioId])
            ->getJson('/api/reservas-mesas/historico?unidade_id='.$this->unidadeId);

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(2, $data);

        $paga = collect($data)->firstWhere('conta_paga', true);
        $this->assertNotNull($paga);
        $this->assertSame(150.50, (float) $paga['valor_conta']);
        $this->assertCount(1, $paga['pagamentos_conta']);
        $this->assertSame('pix', $paga['pagamentos_conta'][0]['tipo']);
        $this->assertSame(150.50, (float) $paga['pagamentos_conta'][0]['valor']);
        $this->assertSame(2, $paga['total_reservas_cliente']);
    }

    public function test_historico_filtra_por_telefone_cliente(): void
    {
        $mesaId = (int) DB::table('mesas')->insertGetId([
            'unidade_id' => $this->unidadeId,
            'nome_mesa' => 'Mesa 2',
            'numero_mesa' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ReservaMesa::create([
            'unidade_id' => $this->unidadeId,
            'mesa_id' => $mesaId,
            'nome_cliente' => 'João',
            'telefone_cliente' => '69911112222',
            'data_reserva' => '2026-07-01',
            'hora_reserva' => '18:00:00',
            'status' => 'finalizada',
            'conta_paga' => true,
            'valor_conta' => 80,
            'pagamentos_conta' => [['tipo' => 'dinheiro', 'valor' => 80]],
        ]);

        ReservaMesa::create([
            'unidade_id' => $this->unidadeId,
            'mesa_id' => $mesaId,
            'nome_cliente' => 'Pedro',
            'telefone_cliente' => '69933334444',
            'data_reserva' => '2026-07-02',
            'hora_reserva' => '18:30:00',
            'status' => 'finalizada',
        ]);

        $response = $this->withHeaders(['X-Usuario-Id' => (string) $this->usuarioId])
            ->getJson('/api/reservas-mesas/historico?unidade_id='.$this->unidadeId.'&telefone_cliente=69911112222');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('João', $data[0]['nome_cliente']);
        $this->assertTrue($data[0]['conta_paga']);
    }
}
