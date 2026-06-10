<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('despesas_fixas_categorias')) {
            return;
        }

        DB::table('despesas_fixas_categorias')
            ->where('nome', 'Internet / telefonia')
            ->update(['nome' => 'Internet', 'updated_at' => now()]);

        // Variações possíveis em bases antigas
        DB::table('despesas_fixas_categorias')
            ->where('nome', 'Internet/telefonia')
            ->update(['nome' => 'Internet', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('despesas_fixas_categorias')) {
            return;
        }

        DB::table('despesas_fixas_categorias')
            ->where('nome', 'Internet')
            ->update(['nome' => 'Internet / telefonia', 'updated_at' => now()]);
    }
};
