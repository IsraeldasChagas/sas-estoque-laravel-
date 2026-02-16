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
        if (Schema::hasTable('estabelecimentos_compra')) {
            return;
        }
        
        Schema::create('estabelecimentos_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lista_id');
            $table->string('nome', 255);
            $table->string('localizacao', 500)->nullable();
            $table->string('forma_pagamento', 100)->nullable();
            $table->text('observacoes')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('atualizado_em')->nullable()->useCurrentOnUpdate();
            
            $table->foreign('lista_id')->references('id')->on('listas_compras')->onDelete('cascade');
            $table->index('lista_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estabelecimentos_compra');
    }
};
