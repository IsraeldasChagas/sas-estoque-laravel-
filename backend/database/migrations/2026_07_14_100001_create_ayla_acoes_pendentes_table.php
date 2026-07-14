<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ações de escrita da Ayla pendentes de confirmação do usuário.
 * Não executar automaticamente em produção sem revisão (php artisan migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ayla_acoes_pendentes')) {
            return;
        }

        Schema::create('ayla_acoes_pendentes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('telegram_user_id', 60)->nullable()->index();
            $table->string('canal', 40)->nullable(); // telegram|mcp|api
            $table->string('modulo', 60)->default('reservas')->index();
            $table->string('acao', 60)->index(); // criar, atualizar, confirmar, ...
            $table->json('payload');
            $table->text('resumo')->nullable();
            $table->string('status', 20)->default('pendente')->index(); // pendente|confirmada|executada|cancelada|expirada|erro
            $table->timestamp('expira_em')->nullable()->index();
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamp('executado_em')->nullable();
            $table->json('resultado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayla_acoes_pendentes');
    }
};
