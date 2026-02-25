<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona coluna origem à tabela movimentacoes para rastrear DASHBOARD ou LISTA_COMPRAS.
     */
    public function up(): void
    {
        if (Schema::hasTable('movimentacoes') && !Schema::hasColumn('movimentacoes', 'origem')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                $table->string('origem', 50)->nullable()->after('observacao');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('movimentacoes') && Schema::hasColumn('movimentacoes', 'origem')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                $table->dropColumn('origem');
            });
        }
    }
};
