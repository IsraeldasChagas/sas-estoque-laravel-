<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_produtos') && ! Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->boolean('controla_estoque_cardapio')->default(true)->after('estoque');
            });
        }

        if (! Schema::hasTable('cardapio_estoque_saldos')) {
            Schema::create('cardapio_estoque_saldos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id');
                $table->unsignedBigInteger('dlv_produto_id');
                $table->decimal('quantidade', 14, 4)->default(0);
                $table->decimal('estoque_minimo', 14, 4)->default(0);
                $table->timestamps();
                $table->unique(['unidade_id', 'dlv_produto_id'], 'cardapio_estoque_saldos_uni_prod_uq');
                $table->index('dlv_produto_id');
            });
        }

        if (! Schema::hasTable('cardapio_estoque_movimentacoes')) {
            Schema::create('cardapio_estoque_movimentacoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id');
                $table->unsignedBigInteger('dlv_produto_id');
                $table->string('tipo', 20);
                $table->string('origem', 40);
                $table->decimal('quantidade', 14, 4);
                $table->decimal('saldo_apos', 14, 4)->default(0);
                $table->unsignedBigInteger('venda_id')->nullable();
                $table->unsignedBigInteger('comanda_id')->nullable();
                $table->unsignedBigInteger('dlv_pedido_id')->nullable();
                $table->unsignedBigInteger('producao_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'dlv_produto_id'], 'cardapio_estoque_mov_uni_prod_idx');
                $table->index('venda_id');
                $table->index('dlv_pedido_id');
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('venda_itens')) {
            Schema::table('venda_itens', function (Blueprint $table) {
                if (! Schema::hasColumn('venda_itens', 'cardapio_produto_id')) {
                    $table->unsignedBigInteger('cardapio_produto_id')->nullable()->after('produto_id');
                    $table->index('cardapio_produto_id');
                }
                if (! Schema::hasColumn('venda_itens', 'cardapio_movimentacao_id')) {
                    $table->unsignedBigInteger('cardapio_movimentacao_id')->nullable()->after('movimentacao_id');
                }
            });

            // Permite prato vendido só pelo cardápio (sem SKU admin).
            if (Schema::hasColumn('venda_itens', 'produto_id')) {
                try {
                    DB::statement('ALTER TABLE venda_itens MODIFY produto_id BIGINT UNSIGNED NULL');
                } catch (\Throwable $e) {
                    // SQLite / drivers sem MODIFY: ignora; testes usam schema próprio.
                }
            }
        }

        if (Schema::hasTable('pdv_comanda_itens') && Schema::hasColumn('pdv_comanda_itens', 'produto_id')) {
            try {
                DB::statement('ALTER TABLE pdv_comanda_itens MODIFY produto_id BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Carga inicial: espelha contador legado dlv_produtos.estoque
        if (Schema::hasTable('dlv_produtos') && Schema::hasTable('cardapio_estoque_saldos') && Schema::hasColumn('dlv_produtos', 'estoque')) {
            $agora = now();
            DB::table('dlv_produtos')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($agora) {
                    foreach ($rows as $row) {
                        $unidadeId = (int) ($row->unidade_id ?? 0);
                        $dlvId = (int) $row->id;
                        if ($unidadeId <= 0 || $dlvId <= 0) {
                            continue;
                        }
                        $qtd = round((float) ($row->estoque ?? 0), 4);
                        $exists = DB::table('cardapio_estoque_saldos')
                            ->where('unidade_id', $unidadeId)
                            ->where('dlv_produto_id', $dlvId)
                            ->exists();
                        if ($exists) {
                            continue;
                        }
                        DB::table('cardapio_estoque_saldos')->insert([
                            'unidade_id' => $unidadeId,
                            'dlv_produto_id' => $dlvId,
                            'quantidade' => $qtd,
                            'estoque_minimo' => 0,
                            'created_at' => $agora,
                            'updated_at' => $agora,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venda_itens')) {
            Schema::table('venda_itens', function (Blueprint $table) {
                if (Schema::hasColumn('venda_itens', 'cardapio_movimentacao_id')) {
                    $table->dropColumn('cardapio_movimentacao_id');
                }
                if (Schema::hasColumn('venda_itens', 'cardapio_produto_id')) {
                    $table->dropIndex(['cardapio_produto_id']);
                    $table->dropColumn('cardapio_produto_id');
                }
            });
        }

        Schema::dropIfExists('cardapio_estoque_movimentacoes');
        Schema::dropIfExists('cardapio_estoque_saldos');

        if (Schema::hasTable('dlv_produtos') && Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->dropColumn('controla_estoque_cardapio');
            });
        }
    }
};
