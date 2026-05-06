<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_candidatos')) {
            return;
        }

        Schema::table('rh_candidatos', function (Blueprint $table) {
            if (! Schema::hasColumn('rh_candidatos', 'grau_instrucao_escolar')) {
                $table->string('grau_instrucao_escolar', 200)->nullable()->after('observacoes_internas');
            }
            if (! Schema::hasColumn('rh_candidatos', 'etnia_racial')) {
                $table->string('etnia_racial', 120)->nullable()->after('grau_instrucao_escolar');
            }
        });
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};
