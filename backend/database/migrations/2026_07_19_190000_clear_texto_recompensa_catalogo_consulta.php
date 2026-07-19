<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_programas')
            || ! Schema::hasColumn('fid_programas', 'texto_recompensa')
            || ! Schema::hasColumn('fid_programas', 'tipo_recompensa_padrao')) {
            return;
        }

        DB::table('fid_programas')
            ->where('tipo_recompensa_padrao', 'catalogo_consulta')
            ->whereNotNull('texto_recompensa')
            ->update(['texto_recompensa' => null]);
    }

    public function down(): void
    {
        // Dados legados não são restaurados.
    }
};
