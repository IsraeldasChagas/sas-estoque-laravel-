<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrcamentosApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['orcamento_historico', 'orcamento_linhas', 'orcamentos', 'orcamento_clientes', 'usuarios'] as $table) {
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

        DB::table('usuarios')->insert([
            'id' => 1,
            'nome' => 'Administrador',
            'perfil' => 'ADMIN',
            'ativo' => 1,
            'unidade_id' => 1,
        ]);

        $migration = require database_path('migrations/2026_07_17_120000_create_orcamentos_tables.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        foreach (['orcamento_historico', 'orcamento_linhas', 'orcamentos', 'orcamento_clientes', 'usuarios'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_cria_orcamento_com_todas_as_etapas_e_calcula_total(): void
    {
        $response = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/orcamentos', [
            'cliente' => [
                'nome' => 'Cliente Teste',
                'whatsapp' => '69999999999',
                'email' => 'cliente@example.com',
            ],
            'tipo' => 'evento',
            'status' => 'pendente',
            'data_orcamento' => '2026-07-17',
            'validade' => '2026-07-24',
            'linhas' => [
                ['tipo_linha' => 'produto_servico', 'descricao' => 'Buffet', 'quantidade' => 2, 'valor_unitario' => 100],
                ['tipo_linha' => 'equipe', 'descricao' => 'Garçom', 'quantidade' => 2, 'horas' => 4, 'valor_unitario' => 10, 'valor_evento' => 50],
                ['tipo_linha' => 'equipamento', 'descricao' => 'Cadeiras', 'quantidade' => 3, 'dias' => 2, 'valor_unitario' => 10],
                ['tipo_linha' => 'consumo', 'descricao' => 'Água', 'quantidade' => 4, 'valor_unitario' => 5],
            ],
            'frete' => ['tipo' => 'entrega', 'valor' => 30, 'distancia_km' => 12],
            'financeiro' => [
                'desconto_percentual' => 10,
                'desconto_valor' => 9,
                'acrescimo_valor' => 10,
                'forma_pagamento' => 'pix',
            ],
            'etapa_wizard' => 8,
        ]);

        $response->assertCreated()
            ->assertJsonPath('cliente.nome', 'Cliente Teste')
            ->assertJsonPath('status', 'pendente')
            ->assertJsonPath('subtotal_produtos', 200)
            ->assertJsonPath('subtotal_equipe', 100)
            ->assertJsonPath('subtotal_equipamentos', 60)
            ->assertJsonPath('subtotal_consumo', 20)
            ->assertJsonPath('subtotal_frete', 30)
            ->assertJsonPath('total_desconto', 50)
            ->assertJsonPath('total', 370)
            ->assertJsonCount(4, 'linhas');

        $this->assertDatabaseCount('orcamentos', 1);
        $this->assertDatabaseCount('orcamento_linhas', 4);
        $this->assertDatabaseCount('orcamento_historico', 1);
    }

    public function test_lista_exibe_proposta_e_altera_status(): void
    {
        $created = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/orcamentos', [
            'cliente' => ['nome' => 'Empresa Alfa'],
            'tipo' => 'servico',
            'data_orcamento' => '2026-07-17',
            'linhas' => [
                ['tipo_linha' => 'produto_servico', 'descricao' => 'Consultoria', 'quantidade' => 3, 'valor_unitario' => 150],
            ],
            'frete' => ['tipo' => 'sem_frete', 'valor' => 0],
            'financeiro' => ['forma_pagamento' => 'pix'],
        ])->assertCreated();

        $id = $created->json('id');

        $this->withHeaders(['X-Usuario-Id' => '1'])
            ->getJson('/api/orcamentos?busca=Alfa')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.total', 450);

        $this->withHeaders(['X-Usuario-Id' => '1'])
            ->patchJson("/api/orcamentos/{$id}/status", ['status' => 'aprovado'])
            ->assertOk()
            ->assertJsonPath('status', 'aprovado');

        $this->assertDatabaseHas('orcamento_historico', [
            'orcamento_id' => $id,
            'acao' => 'status_alterado',
        ]);
    }

    public function test_exige_usuario_ativo(): void
    {
        $this->getJson('/api/orcamentos')->assertUnauthorized();
    }
}
