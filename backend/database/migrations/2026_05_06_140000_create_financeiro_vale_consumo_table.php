<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financeiro_vale_consumo')) {
            return;
        }

        Schema::create('financeiro_vale_consumo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('funcionario_id');
            $table->string('competencia', 7); // YYYY-MM
            $table->decimal('valor_vale', 12, 2)->default(0);
            $table->decimal('valor_consumo', 12, 2)->default(0);
            $table->string('observacao', 500)->nullable();
            $table->timestamps();

            $table->index(['competencia', 'funcionario_id']);
            $table->index('funcionario_id');
        });
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};
