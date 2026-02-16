<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('usuarios')) {
            // Verifica se a coluna ativo já existe
            if (!Schema::hasColumn('usuarios', 'ativo')) {
                Schema::table('usuarios', function (Blueprint $table) {
                    $table->tinyInteger('ativo')->default(1)->after('perfil');
                });
                
                // Define todos os usuários existentes como ativos
                DB::table('usuarios')->update(['ativo' => 1]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'ativo')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('ativo');
            });
        }
    }
};




