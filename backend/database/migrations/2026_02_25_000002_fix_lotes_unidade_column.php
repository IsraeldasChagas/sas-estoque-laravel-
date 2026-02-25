<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Corrige a coluna unidade da tabela lotes para aceitar 'UND'.
     * O erro "Data truncated for column 'unidade'" ocorre quando o ENUM não inclui 'UND'.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            // Altera a coluna para ENUM que inclui UND (compatível com produtos.unidade_base)
            DB::statement("ALTER TABLE lotes MODIFY COLUMN unidade VARCHAR(10) NOT NULL DEFAULT 'UND'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE lotes MODIFY COLUMN unidade ENUM('UN','G','KG','ML','L','PCT','CX') NOT NULL DEFAULT 'UN'");
        }
    }
};
