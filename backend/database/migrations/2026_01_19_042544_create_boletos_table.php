<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boletos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->string('fornecedor');
            $table->string('descricao');
            $table->date('data_vencimento');
            $table->decimal('valor', 10, 2);
            $table->string('categoria')->nullable();
            $table->enum('status', ['A_VENCER', 'VENCIDO', 'PAGO', 'CANCELADO'])->default('A_VENCER');
            $table->date('data_pagamento')->nullable();
            $table->decimal('valor_pago', 10, 2)->nullable();
            $table->decimal('juros_multa', 10, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();
            
            // Indices para melhor performance
            $table->index('unidade_id');
            $table->index('data_vencimento');
            $table->index('status');
            $table->index('usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boletos');
    }
};
