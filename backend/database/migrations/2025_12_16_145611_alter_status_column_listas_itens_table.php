<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Altera a coluna status para suportar PENDENTE, COMPRADO e CANCELADO
     */
    public function up(): void
    {
        Schema::table('listas_itens', function (Blueprint $table) {
            // Altera o ENUM para incluir os novos valores
            // Primeiro converte valores existentes: PLANEJADO -> PENDENTE
            DB::statement("UPDATE listas_itens SET status = 'PENDENTE' WHERE status = 'PLANEJADO'");
            
            // Altera a coluna para o novo ENUM
            DB::statement("ALTER TABLE listas_itens MODIFY COLUMN status ENUM('PENDENTE','COMPRADO','CANCELADO') NOT NULL DEFAULT 'PENDENTE'");
        });
    }

    /**
     * Reverse the migrations.
     * Reverte para o ENUM original
     */
    public function down(): void
    {
        Schema::table('listas_itens', function (Blueprint $table) {
            // Converte PENDENTE de volta para PLANEJADO
            DB::statement("UPDATE listas_itens SET status = 'PLANEJADO' WHERE status = 'PENDENTE'");
            
            // Reverte para o ENUM original
            DB::statement("ALTER TABLE listas_itens MODIFY COLUMN status ENUM('PLANEJADO','COMPRADO') NOT NULL DEFAULT 'PLANEJADO'");
        });
    }
};
