<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listas_compras')) {
            Schema::table('listas_compras', function (Blueprint $table) {
                if (! Schema::hasColumn('listas_compras', 'empresa_id')) {
                    $table->unsignedBigInteger('empresa_id')->nullable()->after('unidade_id');
                    $table->index('empresa_id');
                }
                if (! Schema::hasColumn('listas_compras', 'status_fiscal')) {
                    $table->string('status_fiscal', 32)->default('pendente')->after('status');
                    $table->index('status_fiscal');
                }
            });
        }

        if (! Schema::hasTable('notas_fiscais_entrada')) {
            Schema::create('notas_fiscais_entrada', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('fornecedor_id')->nullable();
                $table->unsignedBigInteger('lista_compra_id')->nullable();
                $table->string('modelo_documento', 4)->nullable();
                $table->string('serie', 10)->nullable();
                $table->string('numero', 20)->nullable();
                $table->string('chave_acesso', 44)->nullable();
                $table->date('data_emissao')->nullable();
                $table->dateTime('data_entrada')->nullable();
                $table->decimal('valor_produtos', 14, 2)->nullable();
                $table->decimal('valor_frete', 14, 2)->nullable();
                $table->decimal('valor_seguro', 14, 2)->nullable();
                $table->decimal('valor_desconto', 14, 2)->nullable();
                $table->decimal('valor_outras_despesas', 14, 2)->nullable();
                $table->decimal('valor_total', 14, 2)->nullable();
                $table->string('status', 32)->default('rascunho');
                $table->text('observacoes')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'chave_acesso']);
                $table->index('lista_compra_id');
            });
        }

        if (! Schema::hasTable('itens_notas_fiscais_entrada')) {
            Schema::create('itens_notas_fiscais_entrada', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('nota_fiscal_entrada_id');
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->unsignedBigInteger('lista_item_id')->nullable();
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->string('ncm', 12)->nullable();
                $table->string('cest', 10)->nullable();
                $table->string('cfop', 6)->nullable();
                $table->string('cst_icms', 5)->nullable();
                $table->string('csosn', 6)->nullable();
                $table->string('origem_mercadoria', 2)->nullable();
                $table->decimal('quantidade', 14, 4)->default(0);
                $table->string('unidade_medida', 10)->nullable();
                $table->decimal('valor_unitario', 14, 4)->nullable();
                $table->decimal('valor_produto', 14, 2)->nullable();
                $table->decimal('valor_desconto', 14, 2)->nullable();
                $table->decimal('valor_frete_rateado', 14, 2)->nullable();
                $table->decimal('valor_seguro_rateado', 14, 2)->nullable();
                $table->decimal('valor_outras_despesas_rateado', 14, 2)->nullable();
                $table->decimal('valor_total_item', 14, 2)->nullable();
                $table->decimal('base_icms', 14, 2)->nullable();
                $table->decimal('aliquota_icms', 8, 4)->nullable();
                $table->decimal('valor_icms', 14, 2)->nullable();
                $table->decimal('base_icms_st', 14, 2)->nullable();
                $table->decimal('aliquota_icms_st', 8, 4)->nullable();
                $table->decimal('valor_icms_st', 14, 2)->nullable();
                $table->decimal('base_pis', 14, 2)->nullable();
                $table->decimal('aliquota_pis', 8, 4)->nullable();
                $table->decimal('valor_pis', 14, 2)->nullable();
                $table->decimal('base_cofins', 14, 2)->nullable();
                $table->decimal('aliquota_cofins', 8, 4)->nullable();
                $table->decimal('valor_cofins', 14, 2)->nullable();
                $table->decimal('base_ipi', 14, 2)->nullable();
                $table->decimal('aliquota_ipi', 8, 4)->nullable();
                $table->decimal('valor_ipi', 14, 2)->nullable();
                $table->decimal('base_cbs', 14, 2)->nullable();
                $table->decimal('aliquota_cbs', 8, 4)->nullable();
                $table->decimal('valor_cbs', 14, 2)->nullable();
                $table->decimal('base_ibs', 14, 2)->nullable();
                $table->decimal('aliquota_ibs', 8, 4)->nullable();
                $table->decimal('valor_ibs', 14, 2)->nullable();
                $table->json('cadastro_fiscal_snapshot')->nullable();
                $table->json('alertas_fiscais')->nullable();
                $table->timestamps();
                $table->index('nota_fiscal_entrada_id');
                $table->index('produto_id');
            });
        }

        if (! Schema::hasTable('creditos_fiscais_entrada')) {
            Schema::create('creditos_fiscais_entrada', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('nota_fiscal_entrada_id');
                $table->unsignedBigInteger('item_nota_fiscal_entrada_id')->nullable();
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->string('tipo_tributo', 20);
                $table->decimal('valor_destacado', 14, 2)->default(0);
                $table->decimal('valor_potencial', 14, 2)->default(0);
                $table->string('status', 32)->default('nao_analisado');
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index(['nota_fiscal_entrada_id', 'tipo_tributo']);
            });
        }

        if (Schema::hasTable('lotes')) {
            Schema::table('lotes', function (Blueprint $table) {
                foreach ([
                    'empresa_id' => 'unsignedBigInteger',
                    'lista_compra_id' => 'unsignedBigInteger',
                    'nota_fiscal_entrada_id' => 'unsignedBigInteger',
                    'item_nota_fiscal_entrada_id' => 'unsignedBigInteger',
                ] as $col => $type) {
                    if (! Schema::hasColumn('lotes', $col)) {
                        $table->{$type}($col)->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('movimentacoes')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                foreach ([
                    'empresa_id' => 'unsignedBigInteger',
                    'lista_compra_id' => 'unsignedBigInteger',
                    'nota_fiscal_entrada_id' => 'unsignedBigInteger',
                    'item_nota_fiscal_entrada_id' => 'unsignedBigInteger',
                ] as $col => $type) {
                    if (! Schema::hasColumn('movimentacoes', $col)) {
                        $table->{$type}($col)->nullable();
                    }
                }
                if (! Schema::hasColumn('movimentacoes', 'tipo_entrada_fiscal')) {
                    $table->string('tipo_entrada_fiscal', 40)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos_fiscais_entrada');
        Schema::dropIfExists('itens_notas_fiscais_entrada');
        Schema::dropIfExists('notas_fiscais_entrada');

        if (Schema::hasTable('movimentacoes')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                foreach (['tipo_entrada_fiscal', 'item_nota_fiscal_entrada_id', 'nota_fiscal_entrada_id', 'lista_compra_id', 'empresa_id'] as $col) {
                    if (Schema::hasColumn('movimentacoes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('lotes')) {
            Schema::table('lotes', function (Blueprint $table) {
                foreach (['item_nota_fiscal_entrada_id', 'nota_fiscal_entrada_id', 'lista_compra_id', 'empresa_id'] as $col) {
                    if (Schema::hasColumn('lotes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('listas_compras')) {
            Schema::table('listas_compras', function (Blueprint $table) {
                foreach (['status_fiscal', 'empresa_id'] as $col) {
                    if (Schema::hasColumn('listas_compras', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
