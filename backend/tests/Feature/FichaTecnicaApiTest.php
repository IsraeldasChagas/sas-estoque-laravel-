<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FichaTecnicaApiTest extends TestCase
{
    public function test_post_ficha_tecnica_com_ingredientes(): void
    {
        if (! Schema::hasTable('fichas_tecnicas')) {
            $this->markTestSkipped('Tabela fichas_tecnicas ausente.');
        }

        $payload = [
            'nome_prato' => 'Prato teste PHPUnit',
            'tempo_preparo' => '20 minutos',
            'responsavel_tecnico' => 'Chef Teste',
            'data_ficha' => '2026-07-24',
            'ingredientes' => [
                [
                    'id' => 999,
                    'nome' => 'Arroz',
                    'quantidade' => 0.5,
                    'unidade_medida' => 'kg',
                    'custo_unitario' => 4.5,
                    'custo_total' => 2.25,
                ],
            ],
        ];

        $res = $this->postJson('/api/fichas-tecnicas', $payload);
        $res->assertCreated();
        $res->assertJsonPath('nome_prato', 'Prato teste PHPUnit');
        $id = (int) $res->json('id');
        $this->assertGreaterThan(0, $id);

        DB::table('fichas_tecnicas')->where('id', $id)->delete();
    }
}
