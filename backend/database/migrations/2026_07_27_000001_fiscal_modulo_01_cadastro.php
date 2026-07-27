<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            Schema::create('empresas', function (Blueprint $table) {
                $table->id();
                $table->string('razao_social', 255);
                $table->string('nome_fantasia', 255)->nullable();
                $table->string('cnpj', 14)->nullable()->unique();
                $table->string('inscricao_estadual', 30)->nullable();
                $table->string('inscricao_municipal', 30)->nullable();
                $table->string('regime_tributario', 40)->nullable();
                $table->string('crt', 2)->nullable();
                $table->char('uf', 2)->nullable();
                $table->string('municipio', 120)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perfis_tributarios')) {
            Schema::create('perfis_tributarios', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 150);
                $table->text('descricao')->nullable();
                $table->string('tipo_fiscal_padrao', 40)->nullable();
                $table->string('ncm_padrao', 8)->nullable();
                $table->string('cest_padrao', 7)->nullable();
                $table->string('cst_icms', 3)->nullable();
                $table->string('csosn', 4)->nullable();
                $table->string('cfop_entrada_padrao', 4)->nullable();
                $table->string('cfop_saida_padrao', 4)->nullable();
                $table->string('tratamento_icms', 80)->nullable();
                $table->string('tratamento_pis', 80)->nullable();
                $table->string('tratamento_cofins', 80)->nullable();
                $table->string('tratamento_ipi', 80)->nullable();
                $table->string('tratamento_cbs', 80)->nullable();
                $table->string('tratamento_ibs', 80)->nullable();
                $table->boolean('monofasico')->default(false);
                $table->boolean('substituicao_tributaria')->default(false);
                $table->boolean('gera_credito_icms')->default(false);
                $table->boolean('gera_credito_pis')->default(false);
                $table->boolean('gera_credito_cofins')->default(false);
                $table->boolean('ativo')->default(true);
                $table->text('observacoes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('unidades') && ! Schema::hasColumn('unidades', 'empresa_id')) {
            Schema::table('unidades', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
                $table->index('empresa_id');
            });
        }

        if (Schema::hasTable('produtos')) {
            Schema::table('produtos', function (Blueprint $table) {
                $cols = [
                    'tipo_fiscal' => fn () => $table->string('tipo_fiscal', 40)->nullable(),
                    'perfil_tributario_id' => fn () => $table->unsignedBigInteger('perfil_tributario_id')->nullable(),
                    'ncm' => fn () => $table->string('ncm', 8)->nullable(),
                    'cest' => fn () => $table->string('cest', 7)->nullable(),
                    'origem_mercadoria' => fn () => $table->string('origem_mercadoria', 2)->nullable(),
                    'cst_icms' => fn () => $table->string('cst_icms', 3)->nullable(),
                    'csosn' => fn () => $table->string('csosn', 4)->nullable(),
                    'cfop_entrada_padrao' => fn () => $table->string('cfop_entrada_padrao', 4)->nullable(),
                    'cfop_saida_padrao' => fn () => $table->string('cfop_saida_padrao', 4)->nullable(),
                    'tratamento_icms' => fn () => $table->string('tratamento_icms', 80)->nullable(),
                    'tratamento_pis' => fn () => $table->string('tratamento_pis', 80)->nullable(),
                    'tratamento_cofins' => fn () => $table->string('tratamento_cofins', 80)->nullable(),
                    'tratamento_ipi' => fn () => $table->string('tratamento_ipi', 80)->nullable(),
                    'tratamento_cbs' => fn () => $table->string('tratamento_cbs', 80)->nullable(),
                    'tratamento_ibs' => fn () => $table->string('tratamento_ibs', 80)->nullable(),
                    'monofasico' => fn () => $table->boolean('monofasico')->nullable(),
                    'substituicao_tributaria' => fn () => $table->boolean('substituicao_tributaria')->nullable(),
                    'gera_credito_icms' => fn () => $table->boolean('gera_credito_icms')->nullable(),
                    'gera_credito_pis' => fn () => $table->boolean('gera_credito_pis')->nullable(),
                    'gera_credito_cofins' => fn () => $table->boolean('gera_credito_cofins')->nullable(),
                    'observacao_fiscal' => fn () => $table->text('observacao_fiscal')->nullable(),
                ];
                foreach ($cols as $name => $add) {
                    if (! Schema::hasColumn('produtos', $name)) {
                        $add();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produtos')) {
            Schema::table('produtos', function (Blueprint $table) {
                foreach ([
                    'observacao_fiscal',
                    'gera_credito_cofins',
                    'gera_credito_pis',
                    'gera_credito_icms',
                    'substituicao_tributaria',
                    'monofasico',
                    'tratamento_ibs',
                    'tratamento_cbs',
                    'tratamento_ipi',
                    'tratamento_cofins',
                    'tratamento_pis',
                    'tratamento_icms',
                    'cfop_saida_padrao',
                    'cfop_entrada_padrao',
                    'csosn',
                    'cst_icms',
                    'origem_mercadoria',
                    'cest',
                    'ncm',
                    'perfil_tributario_id',
                    'tipo_fiscal',
                ] as $col) {
                    if (Schema::hasColumn('produtos', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('unidades') && Schema::hasColumn('unidades', 'empresa_id')) {
            Schema::table('unidades', function (Blueprint $table) {
                $table->dropIndex(['empresa_id']);
                $table->dropColumn('empresa_id');
            });
        }

        Schema::dropIfExists('perfis_tributarios');
        Schema::dropIfExists('empresas');
    }
};
