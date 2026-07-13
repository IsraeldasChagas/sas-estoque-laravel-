<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ayla_usuarios_autorizados')) {
            return;
        }

        Schema::create('ayla_usuarios_autorizados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->string('telegram_user_id', 60)->nullable();
            $table->string('telegram_username', 120)->nullable();
            $table->string('telegram_nome', 160)->nullable();
            $table->string('cargo', 120)->nullable();
            $table->json('unidades_permitidas')->nullable();
            $table->json('modulos_permitidos')->nullable();
            $table->boolean('pode_usar_texto')->default(true);
            $table->boolean('pode_usar_audio')->default(true);
            $table->boolean('pode_consultar_dados')->default(true);
            $table->boolean('pode_executar_acoes')->default(false);
            $table->string('status', 20)->default('pendente'); // pendente|ativo|bloqueado|revogado
            $table->timestamp('ultimo_acesso_em')->nullable();
            $table->unsignedBigInteger('autorizado_por')->nullable();
            $table->timestamp('autorizado_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('usuario_id');
            $table->index('telegram_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayla_usuarios_autorizados');
    }
};
