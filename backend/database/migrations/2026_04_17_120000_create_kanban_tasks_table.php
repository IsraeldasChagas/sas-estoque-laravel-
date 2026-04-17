<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_tasks')) {
            return;
        }

        Schema::create('kanban_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->unsignedBigInteger('unidade_id');
            $table->string('setor', 80);
            $table->string('responsavel', 255)->nullable();
            $table->enum('prioridade', ['baixa', 'media', 'alta'])->default('media');
            $table->enum('status', ['planejamento', 'a_fazer', 'em_execucao', 'aguardando', 'finalizado'])->default('planejamento');
            $table->date('prazo')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['status', 'unidade_id']);
            $table->index(['prioridade']);
            $table->index(['setor']);
            // Sem FK: em alguns ambientes `unidades.id` não é bigint unsigned alinhado ao InnoDB;
            // o vínculo lógico permanece via unidade_id + join nas consultas.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_tasks');
    }
};
