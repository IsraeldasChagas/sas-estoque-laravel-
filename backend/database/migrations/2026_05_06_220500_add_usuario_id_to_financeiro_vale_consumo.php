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
        if (! Schema::hasColumn('financeiro_vale_consumo', 'usuario_id')) {
            Schema::table('financeiro_vale_consumo', function (Blueprint $table) {
                $table->unsignedBigInteger('usuario_id')->nullable()->after('funcionario_id');
                $table->index('usuario_id');
            });
        }

        // Backfill leve: deixa NULL (não há fonte confiável para registros antigos).
        // Se você quiser, depois dá pra preencher manualmente por regra de negócio.
        try {
            DB::statement('UPDATE financeiro_vale_consumo SET usuario_id = NULL WHERE usuario_id IS NULL');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};

