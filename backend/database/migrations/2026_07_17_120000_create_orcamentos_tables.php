<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orcamento_clientes')) {
            Schema::create('orcamento_clientes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
                $table->string('nome', 160);
                $table->string('telefone', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('instagram', 120)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('documento', 30)->nullable();
                $table->string('empresa', 160)->nullable();
                $table->string('origem', 80)->nullable();
                $table->text('observacoes')->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'nome']);
                $table->index('documento');
            });
        }

        if (! Schema::hasTable('orcamentos')) {
            Schema::create('orcamentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
                $table->string('codigo', 40)->nullable()->unique();
                $table->unsignedBigInteger('cliente_id')->nullable()->index();
                $table->string('cliente_nome_snapshot', 160);
                $table->string('responsavel_nome', 160)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('tipo', 40)->default('evento');
                $table->string('status', 30)->default('rascunho');
                $table->date('data_orcamento');
                $table->date('validade')->nullable();
                $table->string('frete_tipo', 30)->default('sem_frete');
                $table->decimal('frete_valor', 14, 2)->default(0);
                $table->decimal('frete_distancia_km', 10, 2)->nullable();
                $table->text('frete_observacoes')->nullable();
                $table->decimal('desconto_percentual', 7, 2)->default(0);
                $table->decimal('desconto_valor', 14, 2)->default(0);
                $table->decimal('acrescimo_valor', 14, 2)->default(0);
                $table->string('forma_pagamento', 40)->nullable();
                $table->text('financeiro_observacoes')->nullable();
                $table->decimal('subtotal_produtos', 14, 2)->default(0);
                $table->decimal('subtotal_equipe', 14, 2)->default(0);
                $table->decimal('subtotal_equipamentos', 14, 2)->default(0);
                $table->decimal('subtotal_consumo', 14, 2)->default(0);
                $table->decimal('subtotal_frete', 14, 2)->default(0);
                $table->decimal('total_desconto', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('lucro_estimado', 14, 2)->nullable();
                $table->decimal('margem_percentual', 7, 2)->nullable();
                $table->text('observacoes')->nullable();
                $table->unsignedTinyInteger('etapa_wizard')->default(1);
                $table->timestamps();

                $table->index(['unidade_id', 'status']);
                $table->index(['data_orcamento', 'tipo']);
            });
        }

        if (! Schema::hasTable('orcamento_linhas')) {
            Schema::create('orcamento_linhas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orcamento_id')->index();
                $table->string('tipo_linha', 30);
                $table->string('descricao', 220);
                $table->decimal('quantidade', 12, 3)->default(1);
                $table->string('unidade_medida', 30)->nullable();
                $table->decimal('horas', 10, 2)->nullable();
                $table->decimal('dias', 10, 2)->nullable();
                $table->decimal('valor_unitario', 14, 2)->default(0);
                $table->decimal('desconto_percentual', 7, 2)->default(0);
                $table->decimal('valor_evento', 14, 2)->nullable();
                $table->decimal('custo_unitario', 14, 2)->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();

                $table->index(['orcamento_id', 'tipo_linha']);
            });
        }

        if (! Schema::hasTable('orcamento_historico')) {
            Schema::create('orcamento_historico', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orcamento_id')->index();
                $table->string('acao', 60);
                $table->json('detalhes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_historico');
        Schema::dropIfExists('orcamento_linhas');
        Schema::dropIfExists('orcamentos');
        Schema::dropIfExists('orcamento_clientes');
    }
};
