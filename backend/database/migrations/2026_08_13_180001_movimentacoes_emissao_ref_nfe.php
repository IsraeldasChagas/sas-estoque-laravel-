<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('movimentacoes')) {
            return;
        }
        Schema::table('movimentacoes', function (Blueprint $table) {
            if (! Schema::hasColumn('movimentacoes', 'emissao_ref')) {
                $table->string('emissao_ref', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('movimentacoes') || ! Schema::hasColumn('movimentacoes', 'emissao_ref')) {
            return;
        }
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->dropColumn('emissao_ref');
        });
    }
};
