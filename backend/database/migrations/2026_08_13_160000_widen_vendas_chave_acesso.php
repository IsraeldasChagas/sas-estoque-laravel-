<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendas') || ! Schema::hasColumn('vendas', 'chave_acesso')) {
            return;
        }
        // Focus às vezes devolve "NFe" + 44 dígitos; coluna antiga era 44.
        DB::statement('ALTER TABLE vendas MODIFY chave_acesso VARCHAR(60) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendas') || ! Schema::hasColumn('vendas', 'chave_acesso')) {
            return;
        }
        DB::statement('ALTER TABLE vendas MODIFY chave_acesso VARCHAR(44) NULL');
    }
};
