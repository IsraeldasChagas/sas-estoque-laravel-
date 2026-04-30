<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_candidatos')) {
            return;
        }
        Schema::table('rh_candidatos', function (Blueprint $table) {
            if (! Schema::hasColumn('rh_candidatos', 'documentacao_token_hash')) {
                $table->string('documentacao_token_hash', 64)->nullable()->after('anonimizado_por');
            }
            if (! Schema::hasColumn('rh_candidatos', 'documentacao_token_gerado_em')) {
                $table->timestamp('documentacao_token_gerado_em')->nullable()->after('documentacao_token_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rh_candidatos')) {
            return;
        }
        Schema::table('rh_candidatos', function (Blueprint $table) {
            if (Schema::hasColumn('rh_candidatos', 'documentacao_token_gerado_em')) {
                $table->dropColumn('documentacao_token_gerado_em');
            }
            if (Schema::hasColumn('rh_candidatos', 'documentacao_token_hash')) {
                $table->dropColumn('documentacao_token_hash');
            }
        });
    }
};
