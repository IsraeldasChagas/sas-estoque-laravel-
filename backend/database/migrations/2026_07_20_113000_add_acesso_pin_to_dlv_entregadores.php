<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_entregadores') && ! Schema::hasColumn('dlv_entregadores', 'acesso_pin')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->string('acesso_pin', 6)->nullable()->after('acesso_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_entregadores') && Schema::hasColumn('dlv_entregadores', 'acesso_pin')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->dropColumn('acesso_pin');
            });
        }
    }
};
