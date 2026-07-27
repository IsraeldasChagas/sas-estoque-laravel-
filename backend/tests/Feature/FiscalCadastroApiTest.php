<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalCadastroApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('unidade_id')->nullable();
        });

        DB::table('usuarios')->insert([
            ['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1, 'unidade_id' => null],
            ['id' => 2, 'nome' => 'Gerente', 'perfil' => 'GERENTE', 'ativo' => 1, 'unidade_id' => null],
            ['id' => 3, 'nome' => 'Estoquista', 'perfil' => 'ESTOQUISTA', 'ativo' => 1, 'unidade_id' => null],
        ]);

        $migration = require database_path('migrations/2026_07_27_000001_fiscal_modulo_01_cadastro.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_fiscal_meta_publico(): void
    {
        $res = $this->getJson('/api/fiscal/meta');
        $res->assertOk();
        $res->assertJsonStructure(['tipos_fiscais', 'regimes_tributarios', 'origens_mercadoria']);
    }

    public function test_admin_cria_empresa_e_gerente_le(): void
    {
        $create = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/empresas', [
            'razao_social' => 'Sabor Paraense LTDA',
            'nome_fantasia' => 'Grupo Sabor',
            'cnpj' => '04.052.123/0001-90',
            'regime_tributario' => 'simples_nacional',
            'uf' => 'PA',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('razao_social', 'Sabor Paraense LTDA');
        $id = (int) $create->json('id');

        $listGerente = $this->withHeaders(['X-Usuario-Id' => '2'])->getJson('/api/fiscal/empresas');
        $listGerente->assertOk();
        $listGerente->assertJsonFragment(['id' => $id, 'razao_social' => 'Sabor Paraense LTDA']);

        $deny = $this->withHeaders(['X-Usuario-Id' => '2'])->postJson('/api/fiscal/empresas', [
            'razao_social' => 'Outra',
        ]);
        $deny->assertStatus(403);

        $noAuth = $this->withHeaders(['X-Usuario-Id' => '3'])->getJson('/api/fiscal/empresas');
        $noAuth->assertStatus(401);
    }

    public function test_admin_cria_perfil_e_sugestao_produto(): void
    {
        $create = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/perfis-tributarios', [
            'nome' => 'Revenda alimentos',
            'tipo_fiscal_padrao' => 'revenda',
            'ncm_padrao' => '21069090',
            'cfop_saida_padrao' => '5102',
        ]);
        $create->assertCreated();
        $id = (int) $create->json('id');

        $sug = $this->withHeaders(['X-Usuario-Id' => '2'])->getJson("/api/fiscal/perfis-tributarios/{$id}/sugestao-produto");
        $sug->assertOk();
        $sug->assertJsonPath('sugestao.tipo_fiscal', 'revenda');
        $sug->assertJsonPath('sugestao.ncm', '21069090');
    }
}
