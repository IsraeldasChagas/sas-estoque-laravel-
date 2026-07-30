<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendas') && ! Schema::hasColumn('vendas', 'idempotency_key')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->after('pdv_terminal');
                $table->unique('idempotency_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas') && Schema::hasColumn('vendas', 'idempotency_key')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
