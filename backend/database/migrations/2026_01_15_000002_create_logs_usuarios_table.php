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
        if (!Schema::hasTable('logs_usuarios')) {
            Schema::create('logs_usuarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ator_id')->comment('ID do usuário que executou a ação');
                $table->unsignedBigInteger('alvo_id')->comment('ID do usuário alvo da ação');
                $table->string('acao', 50)->comment('Ação executada: DESATIVAR ou DELETE');
                $table->integer('qtd_movimentacoes_transferidas')->default(0)->comment('Quantidade de movimentações transferidas');
                $table->text('observacoes')->nullable()->comment('Observações adicionais');
                $table->timestamp('created_at')->useCurrent();
                
                // Índices
                $table->index('ator_id');
                $table->index('alvo_id');
                $table->index('created_at');
                
                // Foreign keys (opcional, pode comentar se causar problemas)
                // $table->foreign('ator_id')->references('id')->on('usuarios')->onDelete('cascade');
                // $table->foreign('alvo_id')->references('id')->on('usuarios')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_usuarios');
    }
};




