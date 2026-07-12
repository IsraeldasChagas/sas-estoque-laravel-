<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ayla_audit_logs')) {
            return;
        }

        Schema::create('ayla_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->string('rota', 255)->nullable();
            $table->string('acao', 120)->nullable();
            $table->text('payload')->nullable();
            $table->text('resposta_resumo')->nullable();
            $table->string('status', 20)->default('ok');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->timestamps();

            $table->index('acao');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayla_audit_logs');
    }
};
