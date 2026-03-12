<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proventos_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provento_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('funcionario_id')->nullable();
            $table->string('acao', 80);
            $table->string('status_anterior', 40)->nullable();
            $table->string('status_novo', 40)->nullable();
            $table->text('descricao')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('canal_otp', 20)->nullable();
            $table->boolean('otp_validado')->nullable();
            $table->text('dados_extras')->nullable(); // JSON para dados adicionais
            $table->timestamp('created_at');

            $table->index('provento_id');
            $table->index('usuario_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proventos_logs');
    }
};
