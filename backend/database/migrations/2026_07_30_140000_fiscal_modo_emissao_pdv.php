<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fiscal_emissao_configs') && ! Schema::hasColumn('fiscal_emissao_configs', 'modo_emissao_pdv')) {
            Schema::table('fiscal_emissao_configs', function (Blueprint $table) {
                $table->string('modo_emissao_pdv', 20)->default('opcional')->after('emitir_nfce_pdv');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fiscal_emissao_configs') && Schema::hasColumn('fiscal_emissao_configs', 'modo_emissao_pdv')) {
            Schema::table('fiscal_emissao_configs', function (Blueprint $table) {
                $table->dropColumn('modo_emissao_pdv');
            });
        }
    }
};
