<?php

namespace Tests\Feature;

use App\Models\FiscalEmissaoConfig;
use App\Services\Fiscal\FiscalNfeTransferenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalNfeTransferenciaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('fiscal_emissao_logs');
        Schema::dropIfExists('movimentacoes');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('fiscal_emissao_configs');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('perfil');
            $table->boolean('ativo')->default(true);
        });
        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Admin', 'perfil' => 'ADMIN', 'ativo' => 1]);

        $cad = require database_path('migrations/2026_07_27_000001_fiscal_modulo_01_cadastro.php');
        $cad->up();
        $end = require database_path('migrations/2026_08_13_180000_empresas_endereco_nfe.php');
        $end->up();
        $em = require database_path('migrations/2026_07_28_190000_fiscal_emissao_config.php');
        $em->up();

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedBigInteger('empresa_id')->nullable();
        });
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('ncm', 8)->nullable();
            $table->string('cfop_saida_padrao', 4)->nullable();
            $table->string('csosn', 4)->nullable();
            $table->string('origem_mercadoria', 2)->nullable();
            $table->string('unidade_base', 10)->nullable();
        });
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('de_unidade_id')->nullable();
            $table->unsignedBigInteger('para_unidade_id')->nullable();
            $table->string('tipo', 24)->nullable();
            $table->decimal('qtd', 14, 4)->default(0);
            $table->decimal('custo_unitario', 14, 4)->nullable();
            $table->string('numero_documento', 60)->nullable();
            $table->string('chave_acesso_documento', 44)->nullable();
            $table->string('modelo_documento', 4)->nullable();
            $table->string('status_documental', 24)->nullable();
            $table->string('emissao_ref', 80)->nullable();
        });
        Schema::create('fiscal_emissao_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('ref', 80)->nullable();
            $table->string('status', 32)->nullable();
            $table->text('mensagem')->nullable();
            $table->json('resposta_json')->nullable();
            $table->timestamps();
        });

        $empA = DB::table('empresas')->insertGetId([
            'razao_social' => 'Empresa A',
            'cnpj' => '56936257000104',
            'inscricao_estadual' => '111',
            'uf' => 'RO',
            'municipio' => 'Porto Velho',
            'logradouro' => 'Rua A',
            'numero' => '10',
            'bairro' => 'Centro',
            'cep' => '76801000',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $empB = DB::table('empresas')->insertGetId([
            'razao_social' => 'Empresa B',
            'cnpj' => '04052123000190',
            'inscricao_estadual' => '222',
            'uf' => 'PA',
            'municipio' => 'Belem',
            'logradouro' => 'Av B',
            'numero' => '20',
            'bairro' => 'Nazare',
            'cep' => '66010000',
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        FiscalEmissaoConfig::query()->create([
            'empresa_id' => $empA,
            'provider' => 'focus_nfe',
            'environment' => 'homologation',
            'api_url' => 'https://homologacao.focusnfe.com.br',
            'api_token' => 'token-nfe-test',
            'serie_nfe' => 1,
            'numero_proximo_nfe' => 10,
            'is_active' => true,
        ]);

        DB::table('unidades')->insert([
            ['id' => 1, 'nome' => 'Loja A', 'empresa_id' => $empA],
            ['id' => 2, 'nome' => 'Loja B', 'empresa_id' => $empB],
        ]);
        DB::table('produtos')->insert([
            'id' => 5,
            'nome' => 'Red Bull',
            'ncm' => '22021000',
            'cfop_saida_padrao' => '5102',
            'csosn' => '102',
            'origem_mercadoria' => '0',
            'unidade_base' => 'UN',
        ]);
        DB::table('movimentacoes')->insert([
            'id' => 7001,
            'produto_id' => 5,
            'de_unidade_id' => 1,
            'para_unidade_id' => 2,
            'tipo' => 'TRANSFERENCIA',
            'qtd' => 3,
            'custo_unitario' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('fiscal_emissao_logs');
        Schema::dropIfExists('movimentacoes');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('fiscal_emissao_configs');
        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_emite_nfe_transferencia_via_focus(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe*' => Http::response([
                'status' => 'autorizado',
                'chave_nfe' => '11260856936257000104550010000000101234567890',
                'numero' => '10',
                'serie' => '1',
            ], 200),
        ]);

        $out = FiscalNfeTransferenciaService::emitirParaMovimentacao(7001);
        $this->assertTrue($out['emitida']);
        $this->assertSame('11260856936257000104550010000000101234567890', $out['chave']);
        $mov = DB::table('movimentacoes')->where('id', 7001)->first();
        $this->assertSame('55', $mov->modelo_documento);
        $this->assertSame('vinculado', $mov->status_documental);
    }

    public function test_rota_emite_nfe_da_movimentacao(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe*' => Http::response([
                'status' => 'autorizado',
                'chave_nfe' => '11260856936257000104550010000000101234567890',
                'numero' => '11',
                'serie' => '1',
            ], 200),
        ]);

        $res = $this->withHeaders(['X-Usuario-Id' => '1'])->postJson('/api/fiscal/movimentacoes/7001/nfe');
        $res->assertOk();
        $res->assertJsonPath('emitida', true);
    }
}
