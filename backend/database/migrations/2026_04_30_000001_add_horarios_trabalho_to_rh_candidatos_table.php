<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_candidatos') && ! Schema::hasColumn('rh_candidatos', 'horarios_trabalho')) {
            Schema::table('rh_candidatos', function (Blueprint $table) {
                $table->string('horarios_trabalho', 255)->nullable()->after('disponibilidade');
            });
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};

