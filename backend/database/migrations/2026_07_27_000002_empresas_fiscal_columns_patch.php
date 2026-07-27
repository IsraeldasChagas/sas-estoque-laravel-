<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante colunas do cadastro fiscal em `empresas` (tabela legada ou criada parcialmente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table) {
            $cols = [
                'nome_fantasia' => fn () => $table->string('nome_fantasia', 255)->nullable(),
                'cnpj' => fn () => $table->string('cnpj', 14)->nullable(),
                'inscricao_estadual' => fn () => $table->string('inscricao_estadual', 30)->nullable(),
                'inscricao_municipal' => fn () => $table->string('inscricao_municipal', 30)->nullable(),
                'regime_tributario' => fn () => $table->string('regime_tributario', 40)->nullable(),
                'crt' => fn () => $table->string('crt', 2)->nullable(),
                'uf' => fn () => $table->char('uf', 2)->nullable(),
                'municipio' => fn () => $table->string('municipio', 120)->nullable(),
                'ativo' => fn () => $table->boolean('ativo')->default(true),
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
        // Colunas podem ser compartilhadas com outro módulo — não remover no down.
    }
};
