<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_folhas_ponto')) {
            return;
        }

        Schema::create('rh_folhas_ponto', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('empresa_nome', 180)->nullable();
            $table->string('empresa_endereco', 400)->nullable();
            $table->string('empresa_cep', 40)->nullable();
            $table->string('empresa_cnpj', 40)->nullable();
            $table->string('empresa_cidade_ano', 140)->nullable();
            $table->string('empresa_email', 180)->nullable();
            $table->string('funcionario_nome', 200);
            $table->string('funcionario_cpf', 32)->nullable();
            $table->string('funcionario_cargo', 140)->nullable();
            $table->string('funcionario_ctps', 80)->nullable();
            $table->longText('dias_json');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['ano', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_folhas_ponto');
    }
};
