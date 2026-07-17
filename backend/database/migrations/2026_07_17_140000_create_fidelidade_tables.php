<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_programas')) {
            Schema::create('fid_programas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->unique();
                $table->boolean('ativo')->default(false);
                $table->string('nome_exibicao', 120)->default('Cartão fidelidade');
                $table->string('modo', 20)->default('selos');
                $table->unsignedSmallInteger('pedidos_meta')->default(10);
                $table->unsignedInteger('pontos_por_selo')->default(1);
                $table->string('tipo_recompensa_padrao', 40)->default('produto');
                $table->unsignedBigInteger('produto_id')->nullable()->index();
                $table->decimal('valor_desconto', 10, 2)->nullable();
                $table->string('texto_recompensa', 500)->nullable();
                $table->unsignedInteger('dias_expiracao_credito')->nullable();
                $table->boolean('permite_ajuste_manual')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fid_contas')) {
            Schema::create('fid_contas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('telefone_normalizado', 20);
                $table->string('cpf_normalizado', 11)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('nome', 160)->nullable();
                $table->string('codigo_fidelidade', 40)->unique();
                $table->string('status', 20)->default('ativo');
                $table->integer('saldo_selos')->default(0);
                $table->integer('saldo_pontos')->default(0);
                $table->unsignedInteger('total_resgates')->default(0);
                $table->string('origem_tipo', 40)->nullable();
                $table->unsignedBigInteger('origem_id')->nullable();
                $table->timestamps();

                $table->unique(['unidade_id', 'telefone_normalizado']);
                $table->index(['unidade_id', 'status']);
                $table->index(['unidade_id', 'cpf_normalizado']);
            });
        }

        if (! Schema::hasTable('fid_ledger')) {
            Schema::create('fid_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->unsignedBigInteger('conta_id')->index();
                $table->string('tipo', 30);
                $table->integer('delta_selos')->default(0);
                $table->integer('delta_pontos')->default(0);
                $table->integer('saldo_selos_apos')->default(0);
                $table->integer('saldo_pontos_apos')->default(0);
                $table->string('descricao', 500)->nullable();
                $table->string('referencia_tipo', 40)->nullable();
                $table->unsignedBigInteger('referencia_id')->nullable();
                $table->string('idempotency_key', 128)->nullable();
                $table->unsignedBigInteger('reverso_de_id')->nullable()->index();
                $table->timestamp('expira_em')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['unidade_id', 'idempotency_key']);
                $table->index(['conta_id', 'created_at']);
                $table->index(['unidade_id', 'tipo']);
            });
        }

        if (! Schema::hasTable('fid_recompensas')) {
            Schema::create('fid_recompensas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->string('titulo', 160);
                $table->string('tipo', 40)->default('produto');
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->decimal('valor_desconto', 10, 2)->nullable();
                $table->unsignedInteger('custo_selos')->default(0);
                $table->unsignedInteger('custo_pontos')->default(0);
                $table->boolean('ativo')->default(true);
                $table->string('texto', 500)->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'ativo']);
            });
        }

        if (! Schema::hasTable('fid_resgates')) {
            Schema::create('fid_resgates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->index();
                $table->unsignedBigInteger('conta_id')->index();
                $table->unsignedBigInteger('recompensa_id')->nullable()->index();
                $table->unsignedBigInteger('ledger_id')->nullable()->index();
                $table->string('status', 20)->default('pendente');
                $table->string('titulo_snapshot', 160)->nullable();
                $table->string('tipo_snapshot', 40)->nullable();
                $table->unsignedInteger('custo_selos')->default(0);
                $table->unsignedInteger('custo_pontos')->default(0);
                $table->timestamp('entregue_em')->nullable();
                $table->unsignedBigInteger('usuario_entrega_id')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacao')->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fid_resgates');
        Schema::dropIfExists('fid_recompensas');
        Schema::dropIfExists('fid_ledger');
        Schema::dropIfExists('fid_contas');
        Schema::dropIfExists('fid_programas');
    }
};
