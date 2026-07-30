<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_contas_assinadas')) {
            Schema::create('pdv_contas_assinadas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
                $table->string('nome', 160);
                $table->unsignedBigInteger('funcionario_id')->nullable()->index();
                $table->string('telefone', 40)->nullable();
                $table->string('observacao', 300)->nullable();
                $table->boolean('ativo')->default(true)->index();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'ativo', 'nome']);
            });
        }

        if (! Schema::hasTable('pdv_conta_assinada_lancamentos')) {
            Schema::create('pdv_conta_assinada_lancamentos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conta_id')->index();
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
                $table->unsignedBigInteger('venda_id')->nullable()->index();
                $table->string('tipo', 20)->default('consumo'); // consumo | quitacao
                $table->decimal('valor', 12, 2);
                $table->string('observacao', 300)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['conta_id', 'tipo']);
            });
        }

        if (Schema::hasTable('vendas') && ! Schema::hasColumn('vendas', 'conta_assinada_id')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->unsignedBigInteger('conta_assinada_id')->nullable()->after('forma_pagamento')->index();
            });
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};
