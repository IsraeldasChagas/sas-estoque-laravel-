<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proventos_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provento_id');
            $table->unsignedBigInteger('funcionario_id');
            $table->string('canal_envio', 20); // whatsapp, email
            $table->string('codigo_hash', 255);
            $table->timestamp('codigo_expira_em');
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->timestamp('validado_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status_envio', 30)->default('enviado'); // enviado, validado, expirado, cancelado
            $table->timestamps();

            $table->index('provento_id');
            $table->index('funcionario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proventos_assinaturas');
    }
};
