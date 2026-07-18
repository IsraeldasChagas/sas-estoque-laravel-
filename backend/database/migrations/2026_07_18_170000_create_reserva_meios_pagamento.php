<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reserva_meios_pagamento')) {
            return;
        }

        Schema::create('reserva_meios_pagamento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('tipo', 20); // pix | maquininha | dinheiro
            $table->string('nome', 160); // recebedor / identificação auditável
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['unidade_id', 'tipo', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_meios_pagamento');
    }
};
