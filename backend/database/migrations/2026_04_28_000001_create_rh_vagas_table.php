<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_vagas')) {
            Schema::create('rh_vagas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('titulo', 160);
                $table->longText('descricao');
                $table->longText('requisitos')->nullable();
                $table->longText('beneficios')->nullable();
                $table->string('unidade', 120)->nullable();
                $table->string('setor', 120)->nullable();
                $table->unsignedInteger('quantidade')->default(1);
                $table->string('tipo_contratacao', 60)->nullable();
                $table->string('status', 30)->default('aberta'); // aberta, pausada, encerrada
                $table->string('slug', 190)->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Migrations no SAS são preferencialmente aditivas (não apagar dados de clientes).
        // Mantemos down vazio por segurança.
    }
};

