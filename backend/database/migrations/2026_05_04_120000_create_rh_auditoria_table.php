<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_auditoria')) {
            return;
        }

        Schema::create('rh_auditoria', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 80);
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('ip', 64)->nullable();
            $table->json('detalhes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_auditoria');
    }
};
