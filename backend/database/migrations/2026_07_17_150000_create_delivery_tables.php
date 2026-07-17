<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            Schema::create('dlv_loja_config', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->unique();
                $table->string('slug', 120)->unique();
                $table->boolean('ativo')->default(true);
                $table->boolean('aberta')->default(false);
                $table->boolean('confirmar_pedidos')->default(true);
                $table->boolean('permite_retirada')->default(true);
                $table->string('frete_modo', 30)->default('fixed');
                $table->decimal('frete_taxa_fixa', 14, 2)->default(0);
                $table->decimal('frete_gratis_acima', 14, 2)->nullable();
                $table->decimal('frete_acrescimo_chuva_percent', 7, 2)->default(0);
                $table->boolean('frete_chuva_ativa')->default(false);
                $table->string('pix_chave', 180)->nullable();
                $table->string('pix_tipo', 40)->nullable();
                $table->string('pix_beneficiario', 160)->nullable();
                $table->string('formas_pagamento', 255)->nullable();
                $table->string('nome_loja', 160)->nullable();
                $table->string('logo_path', 255)->nullable();
                $table->string('banner_path', 255)->nullable();
                $table->string('cor_primaria', 20)->nullable();
                $table->text('descricao')->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('telefone', 30)->nullable();
                $table->text('endereco_texto')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dlv_categorias')) {
            Schema::create('dlv_categorias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('nome', 120);
                $table->unsignedInteger('ordem')->default(0);
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->index(['unidade_id', 'ativo']);
                $table->index(['unidade_id', 'ordem']);
            });
        }

        if (! Schema::hasTable('dlv_produtos')) {
            Schema::create('dlv_produtos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->unsignedBigInteger('categoria_id')->nullable()->index();
                $table->unsignedBigInteger('estoque_produto_id')->nullable()->index();
                $table->string('sku', 80)->nullable();
                $table->string('nome', 180);
                $table->decimal('preco', 14, 2)->default(0);
                $table->text('descricao')->nullable();
                $table->string('foto_path', 255)->nullable();
                $table->boolean('ativo')->default(true);
                $table->boolean('visivel_loja')->default(true);
                $table->boolean('permite_adicionais')->default(false);
                $table->unsignedInteger('acrescimo_escolhas_min')->default(0);
                $table->unsignedInteger('acrescimo_escolhas_max')->nullable();
                $table->unsignedInteger('max_ingredientes_retirar')->nullable();
                $table->string('ingredientes_retirar_ui', 40)->default('checkbox');
                $table->string('acrescimos_loja_ui', 40)->default('stepper');
                $table->string('apresentacao', 80)->nullable();
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();

                $table->index(['unidade_id', 'ativo', 'visivel_loja']);
                $table->index(['unidade_id', 'categoria_id']);
                $table->index(['unidade_id', 'nome']);
            });
        }

        if (! Schema::hasTable('dlv_adicionais')) {
            Schema::create('dlv_adicionais', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('nome', 160);
                $table->string('tipo', 20)->default('acrescentar');
                $table->decimal('preco', 14, 2)->default(0);
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->string('foto_path', 255)->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'ativo']);
                $table->index(['unidade_id', 'tipo']);
            });
        }

        if (! Schema::hasTable('dlv_produto_adicional')) {
            Schema::create('dlv_produto_adicional', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('produto_id')->index();
                $table->unsignedBigInteger('adicional_id')->index();
                $table->timestamps();

                $table->unique(['produto_id', 'adicional_id']);
            });
        }

        if (! Schema::hasTable('dlv_produto_ingredientes')) {
            Schema::create('dlv_produto_ingredientes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('produto_id')->index();
                $table->string('nome', 160);
                $table->string('foto_path', 255)->nullable();
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dlv_frete_faixas_cep')) {
            Schema::create('dlv_frete_faixas_cep', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('cep_inicio', 8);
                $table->string('cep_fim', 8);
                $table->decimal('taxa', 14, 2)->default(0);
                $table->string('label', 120)->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();

                $table->index(['unidade_id', 'ativo']);
                $table->index(['unidade_id', 'cep_inicio', 'cep_fim']);
            });
        }

        if (! Schema::hasTable('dlv_entregadores')) {
            Schema::create('dlv_entregadores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('nome', 160);
                $table->string('whatsapp', 30)->nullable();
                $table->string('telefone', 30)->nullable();
                $table->string('moto_placa', 20)->nullable();
                $table->string('moto_modelo', 80)->nullable();
                $table->string('foto_path', 255)->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();

                $table->index(['unidade_id', 'ativo']);
            });
        }

        if (! Schema::hasTable('dlv_pedidos')) {
            Schema::create('dlv_pedidos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('codigo_publico', 40)->unique();
                $table->string('status', 40)->default('pendente_loja');
                $table->string('canal', 40)->default('admin');
                $table->string('fulfillment', 40)->default('entrega');
                $table->string('cliente_nome', 160);
                $table->string('cliente_telefone', 30)->nullable();
                $table->string('cliente_whatsapp', 30)->nullable();
                $table->text('endereco_texto')->nullable();
                $table->string('endereco_cep', 8)->nullable();
                $table->string('endereco_rua', 180)->nullable();
                $table->string('endereco_numero', 40)->nullable();
                $table->string('endereco_bairro', 120)->nullable();
                $table->string('endereco_cidade', 120)->nullable();
                $table->string('endereco_uf', 2)->nullable();
                $table->text('endereco_complemento')->nullable();
                $table->string('pagamento_forma', 40)->nullable();
                $table->string('pagamento_status', 40)->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('frete_valor', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->unsignedBigInteger('entregador_id')->nullable()->index();
                $table->string('entregador_token', 64)->nullable()->index();
                $table->text('observacoes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'status']);
                $table->index(['unidade_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('dlv_pedido_itens')) {
            Schema::create('dlv_pedido_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pedido_id')->index();
                $table->unsignedBigInteger('produto_id')->nullable()->index();
                $table->string('nome_produto', 180);
                $table->decimal('quantidade', 12, 3)->default(1);
                $table->decimal('preco_unitario', 14, 2)->default(0);
                $table->decimal('preco_adicionais', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->json('opcoes_json')->nullable();
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dlv_pedido_historico')) {
            Schema::create('dlv_pedido_historico', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pedido_id')->index();
                $table->string('status_anterior', 40)->nullable();
                $table->string('status_novo', 40);
                $table->string('acao', 60)->default('status_alterado');
                $table->text('detalhes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dlv_pedido_historico');
        Schema::dropIfExists('dlv_pedido_itens');
        Schema::dropIfExists('dlv_pedidos');
        Schema::dropIfExists('dlv_entregadores');
        Schema::dropIfExists('dlv_frete_faixas_cep');
        Schema::dropIfExists('dlv_produto_ingredientes');
        Schema::dropIfExists('dlv_produto_adicional');
        Schema::dropIfExists('dlv_adicionais');
        Schema::dropIfExists('dlv_produtos');
        Schema::dropIfExists('dlv_categorias');
        Schema::dropIfExists('dlv_loja_config');
    }
};
