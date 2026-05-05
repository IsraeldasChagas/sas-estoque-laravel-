<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_candidatos') && ! Schema::hasColumn('rh_candidatos', 'observacoes_candidato')) {
            Schema::table('rh_candidatos', function (Blueprint $table) {
                $table->string('observacoes_candidato', 500)->nullable()->after('disponibilidade');
            });
        }
    }

    public function down(): void
    {
        // Migração aditiva: sem down destrutivo.
    }
};

