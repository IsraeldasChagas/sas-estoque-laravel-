<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_programas')) {
            return;
        }

        Schema::table('fid_programas', function (Blueprint $table) {
            if (! Schema::hasColumn('fid_programas', 'catalogo_qtd_escolhas')) {
                $table->unsignedSmallInteger('catalogo_qtd_escolhas')->nullable()->after('texto_recompensa');
            }
            if (! Schema::hasColumn('fid_programas', 'catalogo_produtos_json')) {
                $table->json('catalogo_produtos_json')->nullable()->after('catalogo_qtd_escolhas');
            }
        });

        if (Schema::hasColumn('fid_programas', 'tipo_recompensa_padrao')) {
            DB::table('fid_programas')
                ->where('tipo_recompensa_padrao', 'produto')
                ->update(['tipo_recompensa_padrao' => 'catalogo_consulta']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fid_programas')) {
            return;
        }

        Schema::table('fid_programas', function (Blueprint $table) {
            foreach (['catalogo_produtos_json', 'catalogo_qtd_escolhas'] as $col) {
                if (Schema::hasColumn('fid_programas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
