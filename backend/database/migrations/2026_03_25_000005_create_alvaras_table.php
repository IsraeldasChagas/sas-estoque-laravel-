<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alvaras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->string('tipo', 255);
            $table->date('data_inicio');
            $table->date('data_vencimento');
            $table->decimal('valor_pago', 10, 2)->nullable();

            // Anexo (arquivo)
            $table->string('anexo_path')->nullable();
            $table->string('anexo_nome')->nullable();
            $table->string('anexo_tipo', 20)->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index('unidade_id');
            $table->index('data_vencimento');
            $table->index('tipo');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alvaras');
    }
};

