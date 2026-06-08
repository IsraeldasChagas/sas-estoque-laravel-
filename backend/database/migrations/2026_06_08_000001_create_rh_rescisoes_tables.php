<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_rescisoes')) {
            Schema::create('rh_rescisoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('funcionario_id')->nullable();
                $table->string('cargo', 120)->nullable();
                $table->decimal('salario_base', 12, 2)->default(0);
                $table->date('data_admissao')->nullable();
                $table->date('data_demissao')->nullable();
                $table->string('tipo_contrato', 40)->nullable();
                $table->string('tipo_rescisao', 60)->nullable();
                $table->string('aviso_previo_tipo', 40)->nullable();
                $table->unsignedSmallInteger('dias_trabalhados_mes')->default(0);
                $table->decimal('ferias_vencidas', 12, 2)->default(0);
                $table->decimal('ferias_proporcionais', 12, 2)->default(0);
                $table->decimal('decimo_terceiro_proporcional', 12, 2)->default(0);
                $table->decimal('horas_extras', 12, 2)->default(0);
                $table->decimal('adicionais', 12, 2)->default(0);
                $table->decimal('descontos', 12, 2)->default(0);
                $table->decimal('faltas', 12, 2)->default(0);
                $table->decimal('adiantamentos', 12, 2)->default(0);
                $table->decimal('vale_transporte', 12, 2)->default(0);
                $table->decimal('vale_alimentacao', 12, 2)->default(0);
                $table->decimal('fgts_mensal', 12, 2)->default(0);
                $table->decimal('fgts_estimado', 12, 2)->default(0);
                $table->unsignedTinyInteger('multa_fgts_percentual')->default(0);
                $table->decimal('multa_fgts_valor', 12, 2)->default(0);
                $table->decimal('inss_estimado', 12, 2)->default(0);
                $table->decimal('irrf_estimado', 12, 2)->default(0);
                $table->decimal('total_bruto', 12, 2)->default(0);
                $table->decimal('total_descontos', 12, 2)->default(0);
                $table->decimal('total_liquido', 12, 2)->default(0);
                $table->decimal('custo_empresa', 12, 2)->default(0);
                $table->string('status', 30)->default('simulacao');
                $table->text('observacoes')->nullable();
                $table->json('detalhes_calculo')->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamps();

                $table->index('unidade_id');
                $table->index('funcionario_id');
                $table->index('status');
                $table->index('data_demissao');
                $table->index('tipo_rescisao');
            });
        }

        if (! Schema::hasTable('rh_rescisao_cenarios')) {
            Schema::create('rh_rescisao_cenarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rescisao_id')->nullable();
                $table->string('tipo_cenario', 60);
                $table->decimal('total_bruto', 12, 2)->default(0);
                $table->decimal('total_descontos', 12, 2)->default(0);
                $table->decimal('total_liquido', 12, 2)->default(0);
                $table->decimal('custo_empresa', 12, 2)->default(0);
                $table->decimal('fgts_estimado', 12, 2)->default(0);
                $table->decimal('multa_fgts_valor', 12, 2)->default(0);
                $table->text('observacoes')->nullable();
                $table->json('detalhes')->nullable();
                $table->timestamps();

                $table->index('rescisao_id');
                $table->index('tipo_cenario');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_rescisao_cenarios');
        Schema::dropIfExists('rh_rescisoes');
    }
};
