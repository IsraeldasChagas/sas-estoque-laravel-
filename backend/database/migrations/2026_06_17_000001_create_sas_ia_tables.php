<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do módulo SAS IA — conversas, mensagens, logs de ferramentas e documentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->string('titulo', 255)->nullable();
                $table->timestamps();
                $table->index(['usuario_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->string('role', 20); // user, assistant, system, tool
                $table->longText('content')->nullable();
                $table->string('tool_name', 80)->nullable();
                $table->unsignedInteger('tokens_input')->default(0);
                $table->unsignedInteger('tokens_output')->default(0);
                $table->decimal('cost_estimate', 12, 6)->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['conversation_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ai_tool_logs')) {
            Schema::create('ai_tool_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->unsignedBigInteger('message_id')->nullable();
                $table->unsignedBigInteger('usuario_id');
                $table->string('tool_name', 80);
                $table->json('params_json')->nullable();
                $table->text('result_summary')->nullable();
                $table->boolean('success')->default(true);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['usuario_id', 'created_at']);
                $table->index('tool_name');
            });
        }

        if (! Schema::hasTable('ai_documents')) {
            Schema::create('ai_documents', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 255);
                $table->string('tipo', 40)->default('manual'); // manual, procedimento, regra
                $table->longText('conteudo_texto');
                $table->string('arquivo_path', 500)->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->index(['ativo', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_logs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_documents');
    }
};
