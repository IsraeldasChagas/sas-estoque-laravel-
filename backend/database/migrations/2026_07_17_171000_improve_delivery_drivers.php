<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_entregadores') && ! Schema::hasColumn('dlv_entregadores', 'moto_cor')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->string('moto_cor', 64)->nullable()->after('moto_modelo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_entregadores') && Schema::hasColumn('dlv_entregadores', 'moto_cor')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->dropColumn('moto_cor');
            });
        }
    }
};
