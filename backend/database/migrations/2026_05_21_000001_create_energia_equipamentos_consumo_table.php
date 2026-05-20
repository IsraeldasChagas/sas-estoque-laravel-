<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('energia_equipamentos_consumo')) {
            return;
        }

        Schema::create('energia_equipamentos_consumo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id');
            $table->string('setor', 120);
            $table->string('equipamento_nome', 200);
            $table->string('equipamento_tipo', 120)->nullable();
            $table->decimal('potencia_watts', 12, 2)->default(0);
            $table->unsignedSmallInteger('tensao')->default(220);
            $table->unsignedInteger('quantidade')->default(1);
            $table->decimal('horas_por_dia', 8, 2)->default(0);
            $table->unsignedSmallInteger('dias_uso_mes')->default(0);
            $table->decimal('valor_kwh', 10, 4)->default(0);
            $table->decimal('consumo_kwh', 14, 4)->default(0);
            $table->decimal('custo_estimado', 14, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['unidade_id', 'setor']);
            $table->index('equipamento_tipo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('energia_equipamentos_consumo')) {
            return;
        }
        Schema::dropIfExists('energia_equipamentos_consumo');
    }
};
