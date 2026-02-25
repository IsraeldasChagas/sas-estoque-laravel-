<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Garante que a coluna foto existe na tabela usuarios (usada para avatar).
     */
    public function up(): void
    {
        if (!Schema::hasTable('usuarios')) {
            return;
        }
        if (!Schema::hasColumn('usuarios', 'foto')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->string('foto', 255)->nullable()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'foto')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }
};
