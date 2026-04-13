<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuarios')) {
            return;
        }
        if (!Schema::hasColumn('usuarios', 'atende_caixa')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->boolean('atende_caixa')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'atende_caixa')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('atende_caixa');
            });
        }
    }
};
