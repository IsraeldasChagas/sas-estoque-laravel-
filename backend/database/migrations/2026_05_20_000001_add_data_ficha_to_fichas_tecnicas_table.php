<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fichas_tecnicas')) {
            return;
        }
        if (Schema::hasColumn('fichas_tecnicas', 'data_ficha')) {
            return;
        }
        Schema::table('fichas_tecnicas', function (Blueprint $table) {
            $table->date('data_ficha')->nullable()->after('nome_prato');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fichas_tecnicas')) {
            return;
        }
        if (! Schema::hasColumn('fichas_tecnicas', 'data_ficha')) {
            return;
        }
        Schema::table('fichas_tecnicas', function (Blueprint $table) {
            $table->dropColumn('data_ficha');
        });
    }
};
