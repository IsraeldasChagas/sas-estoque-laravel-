<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recibos_ajuda_custo')) {
            return;
        }
        Schema::create('recibos_ajuda_custo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('funcionario_id');
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->string('competencia', 10)->nullable(); // YYYY-MM
            $table->string('finalidade', 80);
            $table->decimal('valor', 12, 2);

            // Evidências / comprovação
            $table->timestamp('confirmado_em')->nullable();
            $table->string('ip_publico', 64)->nullable();
            $table->string('geo', 200)->nullable();
            $table->longText('assinatura_data_url')->nullable();
            $table->longText('foto_data_url')->nullable();

            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestamps();

            $table->index('funcionario_id');
            $table->index('unidade_id');
            $table->index('competencia');
            $table->index('finalidade');
            $table->index('confirmado_em');
            $table->index('criado_por');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_ajuda_custo');
    }
};

