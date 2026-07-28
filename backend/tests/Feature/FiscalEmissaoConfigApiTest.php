<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalEmissaoConfigApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('fiscal_emissao_configs');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
        });

        DB::table('usuarios')->insert([
            ['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1],
            ['id' => 2, 'nome' => 'Gerente', 'perfil' => 'GERENTE', 'ativo' => 1],
        ]);

        $migration = require database_path('migrations/2026_07_27_000001_fiscal_modulo_01_cadastro.php');
        $migration->up();

        $emMigration = require database_path('migrations/2026_07_28_190000_fiscal_emissao_config.php');
        $emMigration->up();

        DB::table('empresas')->insert([
            'razao_social' => 'Teste LTDA',
            'nome_fantasia' => 'Teste',
            'cnpj' => '04052123000190',
            'regime_tributario' => 'simples_nacional',
            'inscricao_estadual' => '123456789',
            'uf' => 'PA',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('fiscal_emissao_configs');
        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_meta_emissao_publico(): void
    {
        $res = $this->getJson('/api/fiscal/emissao/meta');
        $res->assertOk();
        $res->assertJsonStructure(['providers', 'environments', 'fase_emissao']);
    }

    public function test_admin_salva_config_token_criptografado_no_retorno(): void
    {
        $empresaId = (int) DB::table('empresas')->value('id');

        $save = $this->withHeaders(['X-Usuario-Id' => '1'])->putJson("/api/fiscal/emissao/config/{$empresaId}", [
            'provider' => 'focus_nfe',
            'environment' => 'homologation',
            'api_url' => 'https://homologacao.focusnfe.com.br',
            'api_token' => 'token-secreto-fiscal-12345',
            'csc_id' => '1',
            'csc_token' => 'csc-secreto',
            'serie_nfce' => 1,
            'numero_proximo_nfce' => 100,
            'emitir_nfce_pdv' => true,
            'is_active' => true,
        ]);
        $save->assertOk();
        $save->assertJsonPath('config.api_token_configurado', true);
        $this->assertStringNotContainsString('token-secreto', json_encode($save->json()));

        $get = $this->withHeaders(['X-Usuario-Id' => '2'])->getJson("/api/fiscal/emissao/config/{$empresaId}");
        $get->assertOk();
        $get->assertJsonPath('config.api_token_mascarado', fn ($v) => str_contains($v, '•'));
    }

    public function test_gerente_nao_salva(): void
    {
        $empresaId = (int) DB::table('empresas')->value('id');
        $deny = $this->withHeaders(['X-Usuario-Id' => '2'])->putJson("/api/fiscal/emissao/config/{$empresaId}", [
            'provider' => 'focus_nfe',
            'environment' => 'homologation',
        ]);
        $deny->assertStatus(403);
    }
}
