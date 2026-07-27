<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendas')) {
            Schema::create('vendas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('pdv_terminal', 64)->nullable();
                $table->string('status', 24)->default('finalizada');
                $table->decimal('valor_bruto', 14, 2)->default(0);
                $table->decimal('desconto', 14, 2)->default(0);
                $table->decimal('valor_liquido', 14, 2)->default(0);
                $table->decimal('custo_total', 14, 4)->nullable();
                $table->string('forma_pagamento', 40)->nullable();
                $table->string('numero_documento', 60)->nullable();
                $table->string('chave_acesso', 44)->nullable();
                $table->string('status_documento', 24)->nullable();
                $table->dateTime('data_venda');
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'data_venda']);
                $table->index('unidade_id');
            });
        }

        if (! Schema::hasTable('venda_itens')) {
            Schema::create('venda_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('venda_id');
                $table->unsignedBigInteger('produto_id');
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id');
                $table->decimal('quantidade', 14, 4);
                $table->decimal('preco_unitario', 14, 4);
                $table->decimal('desconto', 14, 2)->default(0);
                $table->decimal('valor_total', 14, 2);
                $table->decimal('custo_unitario', 14, 4)->nullable();
                $table->decimal('custo_total', 14, 4)->nullable();
                $table->unsignedBigInteger('movimentacao_id')->nullable();
                $table->json('fiscal_snapshot')->nullable();
                $table->timestamps();
                $table->index('venda_id');
                $table->index('produto_id');
            });
        }

        if (! Schema::hasTable('tributos_venda')) {
            Schema::create('tributos_venda', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('venda_id');
                $table->unsignedBigInteger('venda_item_id')->nullable();
                $table->string('tipo_tributo', 20);
                $table->decimal('base_calculo', 14, 4)->default(0);
                $table->decimal('aliquota', 8, 4)->nullable();
                $table->decimal('valor', 14, 4)->default(0);
                $table->string('status', 24)->default('calculado');
                $table->timestamps();
                $table->index(['venda_id', 'tipo_tributo']);
            });
        }

        if (! Schema::hasTable('venda_fiscal_bloqueios_log')) {
            Schema::create('venda_fiscal_bloqueios_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedBigInteger('empresa_pdv_id')->nullable();
                $table->unsignedBigInteger('empresa_estoque_id')->nullable();
                $table->unsignedBigInteger('unidade_pdv_id')->nullable();
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->decimal('quantidade', 14, 4)->nullable();
                $table->string('motivo', 64)->default('cnpj_incompativel');
                $table->text('detalhe')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('eventos_fiscais') && ! Schema::hasColumn('eventos_fiscais', 'venda_id')) {
            Schema::table('eventos_fiscais', function (Blueprint $table) {
                $table->unsignedBigInteger('venda_id')->nullable()->after('producao_id');
                $table->index('venda_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_fiscal_bloqueios_log');
        Schema::dropIfExists('tributos_venda');
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');

        if (Schema::hasTable('eventos_fiscais') && Schema::hasColumn('eventos_fiscais', 'venda_id')) {
            Schema::table('eventos_fiscais', function (Blueprint $table) {
                $table->dropColumn('venda_id');
            });
        }
    }
};
