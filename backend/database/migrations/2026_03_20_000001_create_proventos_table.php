<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('funcionario_id');
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->string('tipo', 50); // vale, adiantamento, consumo_interno, ajuda_custo, outro
            $table->string('verba'); // campo livre digitável obrigatório
            $table->decimal('valor', 12, 2);
            $table->date('data_provento');
            $table->string('competencia', 20)->nullable();
            $table->text('motivo');
            $table->text('observacao_interna')->nullable();
            $table->string('status', 40)->default('aguardando_autorizacao');
            $table->unsignedBigInteger('criado_por');
            $table->unsignedBigInteger('autorizado_por')->nullable();
            $table->unsignedBigInteger('finalizado_por')->nullable();
            $table->unsignedBigInteger('cancelado_por')->nullable();
            $table->timestamp('data_autorizacao')->nullable();
            $table->timestamp('data_assinatura')->nullable();
            $table->timestamp('data_finalizacao')->nullable();
            $table->timestamp('data_cancelamento')->nullable();
            $table->text('justificativa_cancelamento')->nullable();
            $table->timestamps();

            $table->index('funcionario_id');
            $table->index('unidade_id');
            $table->index('status');
            $table->index('data_provento');
            $table->index('criado_por');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proventos');
    }
};
