<?php

namespace Tests\Feature;

use App\Models\FiscalEmissaoConfig;
use App\Support\FocusNfcePayloadBuilder;
use App\Services\Fiscal\FiscalEmissaoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalNfceCicloApiTest extends TestCase
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
            ['id' => 2, 'nome' => 'Operador', 'perfil' => 'OPERADOR', 'ativo' => 1],
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
            'razao_social' => 'Ciclo Teste LTDA',
            'cnpj' => '04052123000190',
            'regime_tributario' => 'simples_nacional',
            'inscricao_estadual' => '123456789',
            'uf' => 'RO',
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
            'serie_nfce' => 1,
            'numero_proximo_nfce' => 10,
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
            'chave_acesso' => '11260856936257000104650010000000021457179257',
            'numero_documento' => '2',
            'serie_documento' => '1',
            'emissao_ref' => 'sas-v9001-test',
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

    public function test_info_inclui_portal_svrs(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/*' => Http::response(['status' => 'autorizado'], 200),
        ]);

        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->getJson('/api/fiscal/emissao/vendas/9001/documentos');
        $res->assertOk();
        $res->assertJsonPath('consulta_svrs_url', 'https://dfe-portal.svrs.rs.gov.br/NFCe/Consulta');
        $res->assertJsonPath('chave_completa', true);
    }

    public function test_cancela_nfce_autorizada(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'DELETE') {
                return Http::response(['status' => 'cancelado', 'mensagem' => 'Cancelamento homologado'], 200);
            }

            return Http::response(['status' => 'autorizado'], 200);
        });

        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/emissao/vendas/9001/cancelar', [
            'justificativa' => 'Cancelamento de teste NFC-e no PDV',
        ]);
        $res->assertOk();
        $res->assertJsonPath('ok', true);
        $res->assertJsonPath('status', 'cancelado');
        $this->assertSame('cancelado', DB::table('vendas')->where('id', 9001)->value('status_documento'));
    }

    public function test_cancelar_rejeita_justificativa_curta(): void
    {
        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/emissao/vendas/9001/cancelar', [
            'justificativa' => 'curto',
        ]);
        $res->assertStatus(422);
        $res->assertJsonPath('ok', false);
    }

    public function test_transmite_contingencia_quando_sefaz_autoriza(): void
    {
        DB::table('vendas')->where('id', 9001)->update(['status_documento' => 'contingencia']);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce/sas-v9001-test' => Http::response([
                'status' => 'autorizado',
                'chave_nfe' => '11260856936257000104650010000000021457179257',
                'numero' => '2',
                'serie' => '1',
                'contingencia_offline' => true,
                'contingencia_offline_efetivada' => true,
            ], 200),
        ]);

        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/emissao/vendas/9001/transmitir-contingencia');
        $res->assertOk();
        $res->assertJsonPath('status', 'autorizado');
        $this->assertSame('autorizado', DB::table('vendas')->where('id', 9001)->value('status_documento'));
    }

    public function test_inutiliza_numeracao_nfce(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce/inutilizacao' => Http::response([
                'status' => 'autorizado',
                'mensagem_sefaz' => 'Inutilizacao homologada',
            ], 200),
        ]);

        $empresaId = (int) DB::table('empresas')->value('id');
        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/emissao/inutilizacao', [
            'empresa_id' => $empresaId,
            'serie' => '1',
            'numero_inicial' => '5',
            'numero_final' => '6',
            'justificativa' => 'Quebra de sequencia por rejeicao SEFAZ',
        ]);
        $res->assertOk();
        $res->assertJsonPath('ok', true);
    }

    public function test_operador_nao_inutiliza(): void
    {
        $empresaId = (int) DB::table('empresas')->value('id');
        $res = $this->withHeaders(['X-Usuario-Id' => '2'])->postJson('/api/fiscal/emissao/inutilizacao', [
            'empresa_id' => $empresaId,
            'serie' => '1',
            'numero_inicial' => '5',
            'numero_final' => '6',
            'justificativa' => 'Quebra de sequencia por rejeicao SEFAZ',
        ]);
        $res->assertStatus(403);
    }

    public function test_detecta_sefaz_indisponivel(): void
    {
        $this->assertTrue(FiscalEmissaoService::sefazIndisponivel(['error' => 'Servico Paralisado Momentaneamente (108)']));
        $this->assertTrue(FiscalEmissaoService::sefazIndisponivel(['error' => 'cURL error 28: timeout']));
        $this->assertFalse(FiscalEmissaoService::sefazIndisponivel(['error' => 'Rejeicao: duplicidade de NF-e']));
    }

    public function test_codigo_unico_tem_8_digitos(): void
    {
        $c = FocusNfcePayloadBuilder::codigoUnico(9);
        $this->assertSame(8, strlen($c));
        $this->assertTrue(ctype_digit($c));
    }
}
