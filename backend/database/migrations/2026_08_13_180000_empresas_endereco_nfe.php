<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Endereço do destinatário — exigido pela NF-e 55 entre empresas. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }
        Schema::table('empresas', function (Blueprint $table) {
            $cols = [
                'logradouro' => fn () => $table->string('logradouro', 255)->nullable(),
                'numero' => fn () => $table->string('numero', 20)->nullable(),
                'bairro' => fn () => $table->string('bairro', 120)->nullable(),
                'cep' => fn () => $table->string('cep', 8)->nullable(),
                'codigo_municipio' => fn () => $table->string('codigo_municipio', 7)->nullable(),
            ];
            foreach ($cols as $name => $add) {
                if (! Schema::hasColumn('empresas', $name)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }
        Schema::table('empresas', function (Blueprint $table) {
            foreach (['logradouro', 'numero', 'bairro', 'cep', 'codigo_municipio'] as $col) {
                if (Schema::hasColumn('empresas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
