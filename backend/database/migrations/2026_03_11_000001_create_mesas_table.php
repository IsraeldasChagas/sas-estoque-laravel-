<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('numero_mesa', 50);
            $table->string('nome_mesa')->nullable();
            $table->unsignedInteger('capacidade')->default(4);
            $table->string('localizacao')->nullable();
            $table->string('status', 30)->default('livre');
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['unidade_id', 'numero_mesa']);
            $table->index('unidade_id');
            $table->index('status');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
