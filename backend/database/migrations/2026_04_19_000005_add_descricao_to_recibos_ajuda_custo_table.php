<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) {
            return;
        }
        if (Schema::hasColumn('recibos_ajuda_custo', 'descricao')) {
            return;
        }
        Schema::table('recibos_ajuda_custo', function (Blueprint $table) {
            $table->longText('descricao')->nullable()->after('competencia');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) {
            return;
        }
        if (!Schema::hasColumn('recibos_ajuda_custo', 'descricao')) {
            return;
        }
        Schema::table('recibos_ajuda_custo', function (Blueprint $table) {
            $table->dropColumn('descricao');
        });
    }
};
