<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financeiro_categorias')) {
            Schema::create('financeiro_categorias', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120);
                $table->enum('tipo', ['entrada', 'saida'])->default('saida');
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('financeiro_centros_custo')) {
            Schema::create('financeiro_centros_custo', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120);
                $table->string('codigo', 30)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('financeiro_clientes')) {
            Schema::create('financeiro_clientes', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 255);
                $table->string('documento', 20)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('telefone', 30)->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->boolean('ativo')->default(true);
                $table->text('observacoes')->nullable();
                $table->timestamps();
                $table->index('unidade_id');
            });
        }

        if (! Schema::hasTable('financeiro_lancamentos')) {
            Schema::create('financeiro_lancamentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('categoria_id')->nullable();
                $table->unsignedBigInteger('centro_custo_id')->nullable();
                $table->enum('tipo', ['entrada', 'saida'])->default('saida');
                $table->decimal('valor', 14, 2)->default(0);
                $table->string('descricao', 500)->nullable();
                $table->string('forma_pagamento', 80)->nullable();
                $table->date('data_competencia')->nullable();
                $table->date('data_pagamento')->nullable();
                $table->enum('status', ['previsto', 'realizado', 'atrasado', 'cancelado'])->default('previsto');
                $table->text('observacao')->nullable();
                $table->string('anexo_path', 500)->nullable();
                $table->string('origem_tipo', 60)->nullable();
                $table->unsignedBigInteger('origem_id')->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'data_competencia']);
                $table->index(['tipo', 'status']);
            });
        }

        if (! Schema::hasTable('financeiro_contas_receber')) {
            Schema::create('financeiro_contas_receber', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('descricao', 500)->nullable();
                $table->decimal('valor', 14, 2)->default(0);
                $table->unsignedTinyInteger('parcela_num')->default(1);
                $table->unsignedTinyInteger('total_parcelas')->default(1);
                $table->date('data_vencimento')->nullable();
                $table->date('data_recebimento')->nullable();
                $table->string('forma_recebimento', 80)->nullable();
                $table->enum('status', ['aberto', 'recebido', 'vencido', 'cancelado'])->default('aberto');
                $table->text('observacao')->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'data_vencimento']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('financeiro_orcamentos')) {
            Schema::create('financeiro_orcamentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->string('competencia', 7);
                $table->decimal('meta_faturamento', 14, 2)->default(0);
                $table->decimal('meta_despesa', 14, 2)->default(0);
                $table->decimal('meta_lucro', 14, 2)->default(0);
                $table->text('observacoes')->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamps();
                $table->unique(['unidade_id', 'competencia']);
            });
        }

        if (! Schema::hasTable('financeiro_indicadores_cache')) {
            Schema::create('financeiro_indicadores_cache', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->string('competencia', 7);
                $table->json('payload')->nullable();
                $table->timestamp('calculado_em')->nullable();
                $table->timestamps();
                $table->unique(['unidade_id', 'competencia']);
            });
        }

        $this->seedCentrosCusto();
        $this->seedCategorias();
    }

    private function seedCentrosCusto(): void
    {
        if (! Schema::hasTable('financeiro_centros_custo') || DB::table('financeiro_centros_custo')->exists()) {
            return;
        }
        $now = now();
        foreach (['Administrativo', 'Manutenção', 'Estoque', 'Outros'] as $nome) {
            DB::table('financeiro_centros_custo')->insert([
                'nome' => $nome,
                'codigo' => strtoupper(substr(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome), 0, 6)),
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedCategorias(): void
    {
        if (! Schema::hasTable('financeiro_categorias') || DB::table('financeiro_categorias')->exists()) {
            return;
        }
        $now = now();
        $cats = [
            ['Vendas / faturamento', 'entrada'],
            ['Recebimentos diversos', 'entrada'],
            ['Fornecedores', 'saida'],
            ['Compra de mercado', 'saida'],
            ['Folha e proventos', 'saida'],
            ['Despesas fixas', 'saida'],
            ['Impostos', 'saida'],
            ['Investimentos', 'saida'],
            ['Outras despesas', 'saida'],
        ];
        $ord = 0;
        foreach ($cats as [$nome, $tipo]) {
            DB::table('financeiro_categorias')->insert([
                'nome' => $nome,
                'tipo' => $tipo,
                'ativo' => true,
                'ordem' => $ord++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach ([
            'financeiro_indicadores_cache',
            'financeiro_orcamentos',
            'financeiro_contas_receber',
            'financeiro_lancamentos',
            'financeiro_clientes',
            'financeiro_centros_custo',
            'financeiro_categorias',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
