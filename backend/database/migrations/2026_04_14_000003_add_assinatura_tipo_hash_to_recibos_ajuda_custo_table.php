<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) return;
        Schema::table('recibos_ajuda_custo', function (Blueprint $table) {
            if (!Schema::hasColumn('recibos_ajuda_custo', 'assinatura_tipo')) {
                $table->string('assinatura_tipo', 24)->default('desenho')->after('valor'); // desenho | codigo
            }
            if (!Schema::hasColumn('recibos_ajuda_custo', 'assinatura_hash')) {
                $table->string('assinatura_hash', 96)->nullable()->after('assinatura_tipo');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('recibos_ajuda_custo')) return;
        Schema::table('recibos_ajuda_custo', function (Blueprint $table) {
            if (Schema::hasColumn('recibos_ajuda_custo', 'assinatura_hash')) $table->dropColumn('assinatura_hash');
            if (Schema::hasColumn('recibos_ajuda_custo', 'assinatura_tipo')) $table->dropColumn('assinatura_tipo');
        });
    }
};

