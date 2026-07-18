<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            return;
        }

        Schema::table('dlv_loja_config', function (Blueprint $table) {
            if (! Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
                $table->unsignedBigInteger('unidade_fidelidade_id')->nullable()->after('unidade_id');
            }
        });

        // SaborParaense 2: vitrine delivery na unidade 30, reservas/fidelidade na unidade 26.
        if (Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
            DB::table('dlv_loja_config')
                ->where('slug', 'sobor-paraense-2')
                ->update(['unidade_fidelidade_id' => 26]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            return;
        }

        Schema::table('dlv_loja_config', function (Blueprint $table) {
            if (Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
                $table->dropColumn('unidade_fidelidade_id');
            }
        });
    }
};
