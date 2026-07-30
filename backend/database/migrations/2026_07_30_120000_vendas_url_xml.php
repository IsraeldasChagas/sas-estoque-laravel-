<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendas') && ! Schema::hasColumn('vendas', 'url_xml')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->string('url_xml', 500)->nullable()->after('url_danfe');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas') && Schema::hasColumn('vendas', 'url_xml')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->dropColumn('url_xml');
            });
        }
    }
};
