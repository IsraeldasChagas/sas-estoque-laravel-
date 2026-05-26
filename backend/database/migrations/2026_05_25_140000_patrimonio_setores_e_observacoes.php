<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrimonio_setores')) {
            Schema::create('patrimonio_setores', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120);
                $table->text('descricao')->nullable();
                $table->unsignedSmallInteger('ordem')->default(50);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->unique('nome');
            });
        }

        if (Schema::hasTable('patrimonios')) {
            Schema::table('patrimonios', function (Blueprint $table) {
                if (! Schema::hasColumn('patrimonios', 'setor_id')) {
                    $table->unsignedBigInteger('setor_id')->nullable()->after('unidade_id');
                }
                if (! Schema::hasColumn('patrimonios', 'observacoes')) {
                    $table->text('observacoes')->nullable()->after('numero_nf');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patrimonios')) {
            Schema::table('patrimonios', function (Blueprint $table) {
                if (Schema::hasColumn('patrimonios', 'setor_id')) {
                    $table->dropColumn('setor_id');
                }
                if (Schema::hasColumn('patrimonios', 'observacoes')) {
                    $table->dropColumn('observacoes');
                }
            });
        }
        Schema::dropIfExists('patrimonio_setores');
    }
};
