<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garante coluna foto na tabela produtos (imagem do produto).
     */
    public function up(): void
    {
        if (!Schema::hasTable('produtos')) {
            return;
        }
        if (!Schema::hasColumn('produtos', 'foto')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->string('foto', 255)->nullable()->after('nome');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produtos') && Schema::hasColumn('produtos', 'foto')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }
};
