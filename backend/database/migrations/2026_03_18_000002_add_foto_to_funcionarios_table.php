<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funcionarios') && !Schema::hasColumn('funcionarios', 'foto')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                $table->string('foto', 255)->nullable()->after('nome_completo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('funcionarios') && Schema::hasColumn('funcionarios', 'foto')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }
};
