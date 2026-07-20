<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_entregadores')) {
            return;
        }

        Schema::table('dlv_entregadores', function (Blueprint $table) {
            if (! Schema::hasColumn('dlv_entregadores', 'acesso_pin_usado_em')) {
                $table->timestamp('acesso_pin_usado_em')->nullable()->after('acesso_pin');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_entregadores') && Schema::hasColumn('dlv_entregadores', 'acesso_pin_usado_em')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->dropColumn('acesso_pin_usado_em');
            });
        }
    }
};
