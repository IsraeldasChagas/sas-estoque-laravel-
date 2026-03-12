<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('mesa_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('nome_cliente');
            $table->string('telefone_cliente', 30)->nullable();
            $table->date('data_reserva');
            $table->time('hora_reserva');
            $table->unsignedInteger('qtd_pessoas')->default(1);
            $table->string('status', 30)->default('pendente');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index('unidade_id');
            $table->index('mesa_id');
            $table->index('usuario_id');
            $table->index(['data_reserva', 'unidade_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_mesas');
    }
};
