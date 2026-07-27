<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_comandas')) {
            Schema::create('pdv_comandas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id');
                $table->unsignedBigInteger('mesa_id')->nullable();
                $table->unsignedBigInteger('reserva_mesa_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedBigInteger('garcom_usuario_id')->nullable();
                $table->string('origem', 24)->default('mesa');
                $table->string('status', 32)->default('aberta');
                $table->unsignedInteger('pessoas')->default(1);
                $table->decimal('valor_subtotal', 14, 2)->default(0);
                $table->decimal('desconto', 14, 2)->default(0);
                $table->decimal('acrescimo', 14, 2)->default(0);
                $table->decimal('valor_total', 14, 2)->default(0);
                $table->unsignedBigInteger('venda_id')->nullable();
                $table->text('observacao')->nullable();
                $table->dateTime('aberta_em');
                $table->dateTime('fechada_em')->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'status']);
                $table->index('mesa_id');
            });
        }

        if (! Schema::hasTable('pdv_comanda_itens')) {
            Schema::create('pdv_comanda_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('comanda_id');
                $table->unsignedBigInteger('produto_id');
                $table->decimal('quantidade', 14, 4);
                $table->decimal('preco_unitario', 14, 4);
                $table->decimal('desconto', 14, 2)->default(0);
                $table->decimal('valor_total', 14, 2);
                $table->string('status', 24)->default('ativo');
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index('comanda_id');
            });
        }

        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                if (! Schema::hasColumn('vendas', 'mesa_id')) {
                    $table->unsignedBigInteger('mesa_id')->nullable()->after('unidade_id');
                }
                if (! Schema::hasColumn('vendas', 'comanda_id')) {
                    $table->unsignedBigInteger('comanda_id')->nullable()->after('mesa_id');
                }
                if (! Schema::hasColumn('vendas', 'reserva_mesa_id')) {
                    $table->unsignedBigInteger('reserva_mesa_id')->nullable()->after('comanda_id');
                }
                if (! Schema::hasColumn('vendas', 'origem_venda')) {
                    $table->string('origem_venda', 24)->nullable()->after('pdv_terminal');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                foreach (['origem_venda', 'reserva_mesa_id', 'comanda_id', 'mesa_id'] as $col) {
                    if (Schema::hasColumn('vendas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('pdv_comanda_itens');
        Schema::dropIfExists('pdv_comandas');
    }
};
