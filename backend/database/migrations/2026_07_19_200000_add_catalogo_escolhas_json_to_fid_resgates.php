<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_resgates')) {
            return;
        }

        Schema::table('fid_resgates', function (Blueprint $table) {
            if (! Schema::hasColumn('fid_resgates', 'catalogo_escolhas_json')) {
                $table->json('catalogo_escolhas_json')->nullable()->after('observacao');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fid_resgates')) {
            return;
        }

        Schema::table('fid_resgates', function (Blueprint $table) {
            if (Schema::hasColumn('fid_resgates', 'catalogo_escolhas_json')) {
                $table->dropColumn('catalogo_escolhas_json');
            }
        });
    }
};
