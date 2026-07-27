<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalCompraEntradaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('creditos_fiscais_entrada');
        Schema::dropIfExists('itens_notas_fiscais_entrada');
        Schema::dropIfExists('notas_fiscais_entrada');
        Schema::dropIfExists('listas_itens');
        Schema::dropIfExists('listas_compras');
        Schema::dropIfExists('unidades');
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
        ]);

        (require database_path('migrations/2026_07_27_000001_fiscal_modulo_01_cadastro.php'))->up();

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
        });

        (require database_path('migrations/2026_07_27_000003_fiscal_modulo_02_compras_entrada.php'))->up();

        Schema::create('listas_compras', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('responsavel_id')->nullable();
            $table->string('status')->default('RASCUNHO');
            $table->string('status_fiscal', 32)->default('pendente');
            $table->text('observacoes')->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('estoque_lancado_em')->nullable();
        });

        Schema::table('unidades', function (Blueprint $table) {
            if (! Schema::hasColumn('unidades', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable();
            }
        });

        DB::table('empresas')->insert([
            ['id' => 1, 'razao_social' => 'Empresa A', 'cnpj' => '12345678000199', 'regime_tributario' => 'simples_nacional', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'razao_social' => 'Empresa B', 'cnpj' => '98765432000188', 'regime_tributario' => 'lucro_presumido', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('unidades')->insert([
            ['id' => 10, 'nome' => 'Unidade A1', 'empresa_id' => 1, 'ativo' => 1],
            ['id' => 20, 'nome' => 'Unidade B1', 'empresa_id' => 2, 'ativo' => 1],
        ]);
        DB::table('listas_compras')->insert([
            'id' => 1,
            'nome' => 'Compra teste',
            'unidade_id' => 10,
            'empresa_id' => 1,
            'responsavel_id' => 1,
            'status' => 'RASCUNHO',
            'status_fiscal' => 'pendente',
            'criado_em' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('creditos_fiscais_entrada');
        Schema::dropIfExists('itens_notas_fiscais_entrada');
        Schema::dropIfExists('notas_fiscais_entrada');
        Schema::dropIfExists('listas_itens');
        Schema::dropIfExists('listas_compras');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('usuarios');
        parent::tearDown();
    }

    public function test_vincula_empresa_e_bloqueia_unidade_incompativel(): void
    {
        $ok = $this->withHeaders(['X-Usuario-Id' => '1'])->putJson('/api/fiscal/compras/listas/1', [
            'empresa_id' => 1,
        ]);
        $ok->assertOk();
        $ok->assertJsonPath('empresa_id', 1);

        $bad = $this->withHeaders(['X-Usuario-Id' => '1'])->putJson('/api/listas/1', [
            'unidade_id' => 20,
        ]);
        $bad->assertStatus(400);
    }

    public function test_chave_nf_duplicada_por_cnpj(): void
    {
        $chave = str_repeat('1', 44);
        $first = $this->withHeaders(['X-Usuario-Id' => '1'])->putJson('/api/fiscal/compras/listas/1/nota', [
            'chave_acesso' => $chave,
            'numero' => '100',
            'status' => 'validada',
            'itens' => [],
        ]);
        $first->assertOk();

        DB::table('listas_compras')->insert([
            'id' => 2,
            'nome' => 'Outra lista',
            'unidade_id' => 10,
            'empresa_id' => 1,
            'responsavel_id' => 1,
            'status' => 'RASCUNHO',
            'status_fiscal' => 'pendente',
            'criado_em' => now(),
        ]);

        $dup = $this->withHeaders(['X-Usuario-Id' => '1'])->putJson('/api/fiscal/compras/listas/2/nota', [
            'chave_acesso' => $chave,
            'numero' => '101',
            'itens' => [],
        ]);
        $dup->assertStatus(409);
    }
}
