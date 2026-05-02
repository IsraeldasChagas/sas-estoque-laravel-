<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_folhas_ponto')) {
            return;
        }
        if (Schema::hasColumn('rh_folhas_ponto', 'unidade_id')) {
            return;
        }

        Schema::table('rh_folhas_ponto', function (Blueprint $table) {
            $table->unsignedBigInteger('unidade_id')->nullable()->after('mes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rh_folhas_ponto') || ! Schema::hasColumn('rh_folhas_ponto', 'unidade_id')) {
            return;
        }

        Schema::table('rh_folhas_ponto', function (Blueprint $table) {
            $table->dropColumn('unidade_id');
        });
    }
};
