<?php

namespace Tests\Feature;

use App\Models\FiscalEmissaoConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalDocumentoApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('fiscal_emissao_logs');
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');
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
        ]);

        $migration = require database_path('migrations/2026_07_27_000001_fiscal_modulo_01_cadastro.php');
        $migration->up();

        $vendaMigration = require database_path('migrations/2026_07_27_000006_fiscal_modulo_06_venda_pdv.php');
        $vendaMigration->up();

        $emMigration = require database_path('migrations/2026_07_28_190000_fiscal_emissao_config.php');
        $emMigration->up();

        $focusMigration = require database_path('migrations/2026_07_28_200000_vendas_emissao_focus.php');
        $focusMigration->up();

        $xmlMigration = require database_path('migrations/2026_07_30_120000_vendas_url_xml.php');
        $xmlMigration->up();

        $empresaId = DB::table('empresas')->insertGetId([
            'razao_social' => 'Doc Teste LTDA',
            'cnpj' => '04052123000190',
            'regime_tributario' => 'simples_nacional',
            'inscricao_estadual' => '123456789',
            'uf' => 'PA',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        FiscalEmissaoConfig::query()->create([
            'empresa_id' => $empresaId,
            'provider' => 'focus_nfe',
            'environment' => 'homologation',
            'api_url' => 'https://homologacao.focusnfe.com.br',
            'api_token' => 'token-focus-test',
            'emitir_nfce_pdv' => true,
            'is_active' => true,
        ]);

        DB::table('vendas')->insert([
            'id' => 9001,
            'empresa_id' => $empresaId,
            'unidade_id' => 1,
            'data_venda' => now()->toDateString(),
            'valor_liquido' => 10,
            'status_documento' => 'autorizado',
            'chave_acesso' => '35260104052123000190550010000000011000000001',
            'numero_documento' => '1',
            'serie_documento' => '1',
            'emissao_ref' => 'sas-v9001-test',
            'url_danfe' => 'https://homologacao.focusnfe.com.br/danfe/1.pdf',
            'url_xml' => 'https://homologacao.focusnfe.com.br/xml/1.xml',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('fiscal_emissao_logs');
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');
        Schema::dropIfExists('fiscal_emissao_configs');
        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_info_documentos_venda(): void
    {
        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->getJson('/api/fiscal/emissao/vendas/9001/documentos');
        $res->assertOk();
        $res->assertJsonPath('venda_id', 9001);
        $res->assertJsonPath('disponivel', true);
        $res->assertJsonStructure(['documentos' => ['pdf', 'xml']]);
    }

    public function test_baixa_pdf_e_xml_via_focus(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce/sas-v9001-test.pdf' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']),
            'homologacao.focusnfe.com.br/v2/nfce/sas-v9001-test.xml' => Http::response('<nfeProc>ok</nfeProc>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $pdf = $this->withHeaders(['X-Usuario-Id' => '1'])->get('/api/fiscal/emissao/vendas/9001/danfe.pdf');
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xml = $this->withHeaders(['X-Usuario-Id' => '1'])->get('/api/fiscal/emissao/vendas/9001/xml');
        $xml->assertOk();
        $this->assertStringContainsString('<nfeProc>', $xml->getContent());
    }

    public function test_danfe_html_quando_focus_nao_tem_pdf(): void
    {
        DB::table('vendas')->where('id', 9001)->update([
            'url_danfe' => '/notas_fiscais_consumidor/NFe123.html',
        ]);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce/sas-v9001-test.pdf' => Http::response('{"status":"autorizado","chave_nfe":"NFe123"}', 200, ['Content-Type' => 'application/json']),
            'homologacao.focusnfe.com.br/notas_fiscais_consumidor/NFe123.pdf' => Http::response('not found', 404),
            'homologacao.focusnfe.com.br/notas_fiscais_consumidor/NFe123.html' => Http::response('<html><body><h1>DANFE</h1><p>Cupom teste</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $pdf = $this->withHeaders(['X-Usuario-Id' => '1'])->get('/api/fiscal/emissao/vendas/9001/danfe.pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('attachment', (string) $pdf->headers->get('content-disposition'));
    }
}
