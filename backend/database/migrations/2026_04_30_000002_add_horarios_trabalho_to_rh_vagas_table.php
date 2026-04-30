<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_vagas') && ! Schema::hasColumn('rh_vagas', 'horarios_trabalho')) {
            Schema::table('rh_vagas', function (Blueprint $table) {
                $table->string('horarios_trabalho', 255)->nullable()->after('tipo_contratacao');
            });
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};

