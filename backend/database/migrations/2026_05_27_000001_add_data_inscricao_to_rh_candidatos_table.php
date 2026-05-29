<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_candidatos')) {
            return;
        }

        if (! Schema::hasColumn('rh_candidatos', 'data_inscricao')) {
            Schema::table('rh_candidatos', function (Blueprint $table) {
                $table->date('data_inscricao')->nullable()->after('status');
            });
        }

        if (Schema::hasColumn('rh_candidatos', 'data_inscricao') && Schema::hasColumn('rh_candidatos', 'created_at')) {
            DB::table('rh_candidatos')
                ->whereNull('data_inscricao')
                ->whereNotNull('created_at')
                ->update(['data_inscricao' => DB::raw('DATE(created_at)')]);
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};
