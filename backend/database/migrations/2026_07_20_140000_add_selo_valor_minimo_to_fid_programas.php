<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_programas')) {
            return;
        }

        if (! Schema::hasColumn('fid_programas', 'selo_valor_minimo')) {
            Schema::table('fid_programas', function (Blueprint $table) {
                // Conta da reserva precisa atingir este valor (R$) para liberar 1 selo. 0 = sem mínimo.
                $table->decimal('selo_valor_minimo', 12, 2)->default(100)->after('pontos_por_selo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fid_programas') && Schema::hasColumn('fid_programas', 'selo_valor_minimo')) {
            Schema::table('fid_programas', function (Blueprint $table) {
                $table->dropColumn('selo_valor_minimo');
            });
        }
    }
};
