<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adiciona FRUTOS_DO_MAR e OUTROS em categoria, UND em unidade_base.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE produtos MODIFY COLUMN categoria ENUM('CARNES','HORTIFRUTI','SECOS','BEBIDAS','FRUTOS_DO_MAR','LIMPEZA','EMBALAGENS','CONGELADOS','OUTROS') NOT NULL");
        DB::statement("ALTER TABLE produtos MODIFY COLUMN unidade_base ENUM('UN','UND','G','KG','ML','L','PCT','CX') NOT NULL");
    }

    /**
     * Reverte para os valores originais.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE produtos MODIFY COLUMN categoria ENUM('CARNES','HORTIFRUTI','SECOS','BEBIDAS','LIMPEZA','EMBALAGENS','CONGELADOS') NOT NULL");
        DB::statement("ALTER TABLE produtos MODIFY COLUMN unidade_base ENUM('UN','G','KG','ML','L','PCT','CX') NOT NULL");
    }
};
