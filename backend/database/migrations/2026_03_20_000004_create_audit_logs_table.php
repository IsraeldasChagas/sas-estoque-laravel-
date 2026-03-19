<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('acao', 80); // login, logout, acessar_secao, etc.
            $table->string('recurso', 80)->nullable(); // proventos, usuarios, etc.
            $table->unsignedBigInteger('recurso_id')->nullable();
            $table->text('descricao')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('dados_extras')->nullable(); // JSON: geolocalizacao, rede, etc.
            $table->timestamp('created_at');

            $table->index('usuario_id');
            $table->index('recurso');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
