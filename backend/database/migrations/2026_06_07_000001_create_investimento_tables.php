<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Investimento (Tesouraria e Reservas Empresariais).
 * Migrations aditivas — não remove colunas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('investimento_reservas')) {
            Schema::create('investimento_reservas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable();
                // rescisoes, ferias, decimo_terceiro, impostos, emergencia, expansao, outros
                $table->string('objetivo', 40);
                $table->decimal('valor_inicial', 14, 2)->default(0);
                $table->decimal('aporte_mensal', 14, 2)->default(0);
                $table->unsignedSmallInteger('prazo_meses')->nullable();
                $table->date('data_alvo')->nullable();
                $table->text('observacoes')->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'objetivo']);
                $table->index('data_alvo');
            });
        }

        if (! Schema::hasTable('investimento_carteira')) {
            Schema::create('investimento_carteira', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->date('data_compra');
                $table->string('instituicao', 255);
                // tesouro_selic, tesouro_ipca, tesouro_prefixado, cdb_liquidez, fundo_di, outros
                $table->string('tipo_investimento', 40);
                $table->decimal('valor_aplicado', 14, 2);
                $table->decimal('taxa_contratada', 8, 4)->nullable(); // % a.a.
                $table->decimal('taxa_mensal', 8, 4)->nullable(); // % a.m.
                $table->string('liquidez', 20)->default('media'); // alta, media, baixa
                $table->date('vencimento')->nullable();
                $table->unsignedBigInteger('reserva_id')->nullable();
                // ativo, resgatado, vencido
                $table->string('status', 20)->default('ativo');
                $table->text('observacoes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->index(['unidade_id', 'status']);
                $table->index('vencimento');
                $table->index('reserva_id');
            });
        }

        if (! Schema::hasTable('investimento_resgates')) {
            Schema::create('investimento_resgates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('carteira_id');
                $table->date('data_resgate');
                $table->decimal('valor_resgatado', 14, 2);
                $table->decimal('valor_bruto', 14, 2)->nullable();
                $table->decimal('imposto', 14, 2)->default(0);
                $table->decimal('valor_liquido', 14, 2)->nullable();
                $table->text('observacoes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->index(['carteira_id', 'data_resgate']);
            });
        }
    }

    public function down(): void
    {
        // Intencionalmente vazio — evita perda de dados em rollback acidental.
    }
};
