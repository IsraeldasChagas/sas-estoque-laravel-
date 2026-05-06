<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return;
        }
        if (! Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            Schema::table('financeiro_vale_consumo', function (Blueprint $table) {
                $table->date('data_lancamento')->nullable()->after('funcionario_id');
            });
        }
        // Preenche registros antigos (competência → dia 1 do mês).
        if (Schema::hasColumn('financeiro_vale_consumo', 'data_lancamento')) {
            DB::table('financeiro_vale_consumo')
                ->whereNull('data_lancamento')
                ->update([
                    'data_lancamento' => DB::raw("CONCAT(competencia, '-01')"),
                ]);
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};
