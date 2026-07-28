<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_produtos') && ! Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->unsignedBigInteger('ficha_tecnica_id')->nullable()->after('estoque_produto_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_produtos') && Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->dropColumn('ficha_tecnica_id');
            });
        }
    }
};
