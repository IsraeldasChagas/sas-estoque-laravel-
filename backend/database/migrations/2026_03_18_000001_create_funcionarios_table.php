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
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('cpf', 14)->unique();
            $table->date('data_nascimento')->nullable();
            $table->string('sexo', 20)->nullable();
            $table->string('estado_civil', 30)->nullable();
            $table->string('cargo');
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('data_admissao')->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->boolean('possui_acesso')->default(false);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funcionarios');
    }
};
