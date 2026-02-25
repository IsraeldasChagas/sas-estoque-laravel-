<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Corrige a coluna unidade da tabela movimentacoes para aceitar 'UND'.
     * Mesmo erro de truncamento que ocorria em lotes - ENUM não incluía 'UND'.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE movimentacoes MODIFY COLUMN unidade VARCHAR(10) NOT NULL DEFAULT 'UND'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE movimentacoes MODIFY COLUMN unidade ENUM('UN','G','KG','ML','L','PCT','CX') NOT NULL DEFAULT 'UN'");
        }
    }
};
