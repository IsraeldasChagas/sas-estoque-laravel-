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
        Schema::create('logs_etiquetas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lote_id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('acao', 50)->default('imprimir_etiqueta');
            $table->timestamp('data_hora');
            $table->timestamps();
            
            // Índices para melhor performance (sem foreign keys para evitar problemas de compatibilidade)
            $table->index('lote_id');
            $table->index('usuario_id');
            $table->index('data_hora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_etiquetas');
    }
};
