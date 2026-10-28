<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contagem_inventarios')) {
            Schema::create('contagem_inventarios', function (Blueprint $table) {
                $table->id();
                $table->string('numero', 20)->unique();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('status', 20)->default('rascunho')->index();
                $table->string('tipo', 20)->default('completa');
                $table->boolean('cega')->default(false);
                $table->string('categoria', 120)->nullable();
                $table->text('produto_ids')->nullable();
                $table->text('equipe_ids')->nullable();
                $table->text('observacoes')->nullable();
                $table->unsignedBigInteger('aberto_por')->nullable();
                $table->dateTime('aberto_em')->nullable();
                $table->dateTime('referencia_em')->nullable();
                $table->dateTime('encerrado_em')->nullable();
                $table->unsignedBigInteger('encerrado_por')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contagem_itens')) {
            Schema::create('contagem_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventario_id')->index();
                $table->unsignedBigInteger('produto_id')->index();
                $table->decimal('saldo_referencia', 15, 4)->default(0);
                $table->decimal('custo_unitario', 15, 4)->default(0);
                $table->string('status', 30)->default('pendente');
                $table->timestamps();
                $table->unique(['inventario_id', 'produto_id']);
            });
        }

        if (! Schema::hasTable('contagem_lancamentos')) {
            Schema::create('contagem_lancamentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventario_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->unsignedSmallInteger('rodada')->default(1);
                $table->decimal('quantidade', 15, 4);
                $table->decimal('saldo_sistema', 15, 4)->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacao')->nullable();
                $table->text('justificativa')->nullable();
                $table->dateTime('contado_em');
            });
        }

        if (! Schema::hasTable('contagem_decisoes')) {
            Schema::create('contagem_decisoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventario_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->string('decisao', 20);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('justificativa')->nullable();
                $table->decimal('diferenca', 15, 4)->default(0);
                $table->decimal('valor_financeiro', 15, 2)->default(0);
                $table->unsignedBigInteger('movimentacao_id')->nullable();
                $table->dateTime('decidido_em');
            });
        }

        if (! Schema::hasTable('contagem_auditoria')) {
            Schema::create('contagem_auditoria', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventario_id')->index();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('acao', 60);
                $table->text('detalhe')->nullable();
                $table->dateTime('created_at');
            });
        }

        if (! Schema::hasTable('contagem_configuracoes')) {
            Schema::create('contagem_configuracoes', function (Blueprint $table) {
                $table->id();
                $table->boolean('cega_padrao')->default(false);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contagem_configuracoes');
        Schema::dropIfExists('contagem_auditoria');
        Schema::dropIfExists('contagem_decisoes');
        Schema::dropIfExists('contagem_lancamentos');
        Schema::dropIfExists('contagem_itens');
        Schema::dropIfExists('contagem_inventarios');
    }
};
