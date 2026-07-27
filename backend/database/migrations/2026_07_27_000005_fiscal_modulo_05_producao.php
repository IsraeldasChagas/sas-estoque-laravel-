<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fichas_tecnicas')) {
            Schema::table('fichas_tecnicas', function (Blueprint $table) {
                foreach ([
                    'empresa_id' => 'unsignedBigInteger',
                    'produto_final_id' => 'unsignedBigInteger',
                    'rendimento_quantidade' => ['decimal', 14, 4],
                    'rendimento_unidade' => 'string:20',
                    'versao' => 'unsignedInteger',
                    'ativo' => 'boolean',
                    'observacao' => 'text',
                    'ficha_origem_id' => 'unsignedBigInteger',
                ] as $col => $def) {
                    if (Schema::hasColumn('fichas_tecnicas', $col)) {
                        continue;
                    }
                    if (is_array($def) && ($def[0] ?? '') === 'decimal') {
                        $table->decimal($col, (int) ($def[1] ?? 14), (int) ($def[2] ?? 4))->nullable();
                    } elseif (str_starts_with((string) $def, 'string:')) {
                        $table->string($col, (int) explode(':', (string) $def)[1])->nullable();
                    } elseif ($def === 'unsignedBigInteger') {
                        $table->unsignedBigInteger($col)->nullable();
                    } elseif ($def === 'unsignedInteger') {
                        $table->unsignedInteger($col)->default(1);
                    } elseif ($def === 'boolean') {
                        $table->boolean($col)->default(true);
                    } elseif ($def === 'text') {
                        $table->text($col)->nullable();
                    }
                }
            });
        }

        if (! Schema::hasTable('ficha_tecnica_itens')) {
            Schema::create('ficha_tecnica_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ficha_tecnica_id');
                $table->unsignedBigInteger('produto_insumo_id');
                $table->decimal('quantidade_padrao', 14, 4);
                $table->string('unidade_medida', 20)->nullable();
                $table->decimal('percentual_perda_prevista', 8, 4)->nullable();
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index('ficha_tecnica_id');
                $table->index('produto_insumo_id');
            });
        }

        if (! Schema::hasTable('producoes')) {
            Schema::create('producoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id');
                $table->unsignedBigInteger('ficha_tecnica_id')->nullable();
                $table->unsignedInteger('ficha_versao')->nullable();
                $table->unsignedBigInteger('produto_final_id');
                $table->decimal('quantidade_planejada', 14, 4);
                $table->decimal('quantidade_produzida', 14, 4)->nullable();
                $table->dateTime('data_producao')->nullable();
                $table->string('status', 24)->default('rascunho');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->decimal('custo_insumos', 14, 4)->nullable();
                $table->decimal('custo_adicional', 14, 4)->default(0);
                $table->decimal('custo_total', 14, 4)->nullable();
                $table->decimal('custo_unitario', 14, 4)->nullable();
                $table->decimal('custo_teorico', 14, 4)->nullable();
                $table->text('observacao')->nullable();
                $table->unsignedBigInteger('lote_final_id')->nullable();
                $table->unsignedBigInteger('movimentacao_entrada_id')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'status']);
                $table->index('unidade_id');
                $table->index('produto_final_id');
            });
        }

        if (! Schema::hasTable('producao_insumos')) {
            Schema::create('producao_insumos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producao_id');
                $table->unsignedBigInteger('produto_id');
                $table->decimal('quantidade_prevista', 14, 4);
                $table->decimal('quantidade_real', 14, 4)->nullable();
                $table->decimal('custo_total', 14, 4)->nullable();
                $table->unsignedBigInteger('movimentacao_id')->nullable();
                $table->timestamps();
                $table->index('producao_id');
            });
        }

        if (! Schema::hasTable('producao_lotes')) {
            Schema::create('producao_lotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producao_id');
                $table->unsignedBigInteger('producao_insumo_id');
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->string('codigo_lote', 255)->nullable();
                $table->decimal('quantidade_consumida', 14, 4);
                $table->decimal('custo_unitario', 14, 4)->nullable();
                $table->decimal('custo_total', 14, 4)->nullable();
                $table->timestamps();
                $table->index('producao_id');
            });
        }

        if (Schema::hasTable('movimentacoes') && ! Schema::hasColumn('movimentacoes', 'producao_id')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                $table->unsignedBigInteger('producao_id')->nullable()->after('lista_compra_id');
                $table->index('producao_id');
            });
        }

        if (Schema::hasTable('lotes') && ! Schema::hasColumn('lotes', 'producao_id')) {
            Schema::table('lotes', function (Blueprint $table) {
                $table->unsignedBigInteger('producao_id')->nullable();
            });
        }

        if (Schema::hasTable('eventos_fiscais') && ! Schema::hasColumn('eventos_fiscais', 'producao_id')) {
            Schema::table('eventos_fiscais', function (Blueprint $table) {
                $table->unsignedBigInteger('producao_id')->nullable()->after('movimentacao_id');
                $table->index('producao_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producao_lotes');
        Schema::dropIfExists('producao_insumos');
        Schema::dropIfExists('producoes');
        Schema::dropIfExists('ficha_tecnica_itens');

        if (Schema::hasTable('eventos_fiscais') && Schema::hasColumn('eventos_fiscais', 'producao_id')) {
            Schema::table('eventos_fiscais', function (Blueprint $table) {
                $table->dropColumn('producao_id');
            });
        }
        if (Schema::hasTable('lotes') && Schema::hasColumn('lotes', 'producao_id')) {
            Schema::table('lotes', function (Blueprint $table) {
                $table->dropColumn('producao_id');
            });
        }
        if (Schema::hasTable('movimentacoes') && Schema::hasColumn('movimentacoes', 'producao_id')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                $table->dropColumn('producao_id');
            });
        }

        if (Schema::hasTable('fichas_tecnicas')) {
            Schema::table('fichas_tecnicas', function (Blueprint $table) {
                foreach (['ficha_origem_id', 'observacao', 'ativo', 'versao', 'rendimento_unidade', 'rendimento_quantidade', 'produto_final_id', 'empresa_id'] as $col) {
                    if (Schema::hasColumn('fichas_tecnicas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
