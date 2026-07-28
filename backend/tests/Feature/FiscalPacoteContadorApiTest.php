<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class FiscalPacoteContadorApiTest extends TestCase
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
            ['id' => 2, 'nome' => 'Op', 'perfil' => 'ESTOQUISTA', 'ativo' => 1],
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

    public function test_meta_pacote_contador(): void
    {
        $res = $this->getJson('/api/fiscal/pacote-contador/meta');
        $res->assertOk();
        $res->assertJsonFragment(['formato' => 'zip']);
    }

    public function test_preview_requer_auth_e_empresa(): void
    {
        $empresaId = (int) DB::table('empresas')->value('id');

        $this->getJson('/api/fiscal/pacote-contador/preview?empresa_id=' . $empresaId)
            ->assertStatus(401);

        $this->withHeaders(['X-Usuario-Id' => '2'])
            ->getJson('/api/fiscal/pacote-contador/preview?empresa_id=' . $empresaId)
            ->assertStatus(401);

        $res = $this->withHeaders(['X-Usuario-Id' => '1'])
            ->getJson('/api/fiscal/pacote-contador/preview?empresa_id=' . $empresaId . '&mes=2026-07');
        $res->assertOk();
        $res->assertJsonStructure(['contagens', 'empresa', 'periodo', 'disclaimer']);
        $res->assertJsonPath('contagens.vendas', 0);
    }

    public function test_download_zip_quando_zip_disponivel(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Extensão ZipArchive não disponível.');
        }

        $empresaId = (int) DB::table('empresas')->value('id');
        $res = $this->withHeaders(['X-Usuario-Id' => '1'])
            ->get('/api/fiscal/pacote-contador/download?empresa_id=' . $empresaId . '&mes=2026-07');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('pacote-contador-', (string) $res->headers->get('Content-Disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'test-pacote-');
        file_put_contents($tmp, $res->getContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('LEIA-ME.txt'));
        $zip->close();
        @unlink($tmp);
    }
}
