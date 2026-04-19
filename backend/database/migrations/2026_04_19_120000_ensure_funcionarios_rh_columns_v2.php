<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante colunas RH em servidores que já rodaram migrate antes do ajuste da 2026_04_17.
 * Idempotente: só adiciona o que faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcionarios')) {
            return;
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('funcionarios', 'escolaridade')) {
                $table->string('escolaridade', 80)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'formacao_json')) {
                $table->longText('formacao_json')->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'banco')) {
                $table->string('banco', 100)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'agencia')) {
                $table->string('agencia', 20)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'conta')) {
                $table->string('conta', 20)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'conta_digito')) {
                $table->string('conta_digito', 5)->nullable();
            }
            if (! Schema::hasColumn('funcionarios', 'pix')) {
                $table->string('pix', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
