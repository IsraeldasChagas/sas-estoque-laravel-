<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('despesas_fixas')) {
            return;
        }
        Schema::create('despesas_fixas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 180);
            $table->unsignedBigInteger('categoria_id');
            $table->decimal('valor', 14, 2);
            $table->unsignedTinyInteger('dia_vencimento');
            $table->string('fornecedor', 160)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('status', 20)->default('ativo'); // ativo | pausado
            $table->boolean('aplica_todas_unidades')->default(false);
            $table->json('unidade_ids')->nullable();
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestamps();

            $table->foreign('categoria_id')->references('id')->on('despesas_fixas_categorias')->onDelete('restrict');
            $table->index('categoria_id');
            $table->index('status');
            $table->index('dia_vencimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas_fixas');
    }
};
