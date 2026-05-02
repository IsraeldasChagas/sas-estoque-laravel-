<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Carteira de Trabalho (CTPS) — texto livre (número, série, UF). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcionarios')) {
            return;
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('funcionarios', 'ctps')) {
                $table->string('ctps', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
