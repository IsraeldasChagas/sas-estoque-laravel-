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
        Schema::create('fornecedores_backup', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fornecedor_id_original');
            $table->json('dados_fornecedor');
            $table->dateTime('data_backup');
            $table->unsignedBigInteger('usuario_exclusao')->nullable();
            $table->string('motivo_exclusao')->nullable();
            $table->boolean('restaurado')->default(false);
            $table->timestamps();
            
            $table->index('fornecedor_id_original');
            $table->index('data_backup');
            $table->index('restaurado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fornecedores_backup');
    }
};
