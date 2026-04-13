<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fechamentos_caixa')) {
            return;
        }
        Schema::create('fechamentos_caixa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registrado_por_usuario_id')->nullable();
            $table->unsignedBigInteger('unidade_id')->nullable();
            $table->date('data_fechamento');
            $table->string('hora_fechamento', 16)->nullable();
            $table->string('operador_nome', 500)->nullable();
            $table->unsignedBigInteger('operador_usuario_id')->nullable();
            $table->string('sistema_pdv', 200)->nullable();
            $table->string('maquinha', 120)->nullable();
            $table->text('observacoes')->nullable();
            $table->longText('linhas_json');
            $table->decimal('total_referencia', 15, 2)->default(0);
            $table->decimal('total_informado', 15, 2)->default(0);
            $table->decimal('saldo_liquido', 15, 2)->default(0);
            $table->boolean('sem_quebra')->default(false);
            $table->timestamps();

            $table->index(['data_fechamento', 'created_at']);
            $table->index('unidade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos_caixa');
    }
};
