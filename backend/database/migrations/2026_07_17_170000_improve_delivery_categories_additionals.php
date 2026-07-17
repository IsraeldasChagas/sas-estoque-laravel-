<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_categorias')) {
            DB::table('dlv_categorias')->where('ordem', '>', 65535)->update(['ordem' => 65535]);
            Schema::table('dlv_categorias', function (Blueprint $table) {
                $table->string('nome', 255)->change();
                $table->unsignedSmallInteger('ordem')->default(0)->change();
            });
        }

        if (Schema::hasTable('dlv_adicionais')) {
            DB::table('dlv_adicionais')->whereRaw('LENGTH(nome) > 120')->update([
                'nome' => DB::raw('SUBSTR(nome, 1, 120)'),
            ]);
            DB::table('dlv_adicionais')->where('ordem', '>', 9999)->update(['ordem' => 9999]);
            Schema::table('dlv_adicionais', function (Blueprint $table) {
                $table->string('nome', 120)->change();
                $table->unsignedSmallInteger('ordem')->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_categorias')) {
            DB::table('dlv_categorias')->whereRaw('LENGTH(nome) > 120')->update([
                'nome' => DB::raw('SUBSTR(nome, 1, 120)'),
            ]);
            Schema::table('dlv_categorias', function (Blueprint $table) {
                $table->string('nome', 120)->change();
                $table->unsignedInteger('ordem')->default(0)->change();
            });
        }

        if (Schema::hasTable('dlv_adicionais')) {
            Schema::table('dlv_adicionais', function (Blueprint $table) {
                $table->string('nome', 160)->change();
                $table->unsignedInteger('ordem')->default(0)->change();
            });
        }
    }
};
