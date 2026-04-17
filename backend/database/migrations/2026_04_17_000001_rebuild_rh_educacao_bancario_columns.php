<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcionarios')) {
            return;
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            // Apaga (se existir) para recriar do zero
            foreach (['escolaridade', 'formacao_json', 'banco', 'agencia', 'conta', 'conta_digito', 'pix'] as $col) {
                if (Schema::hasColumn('funcionarios', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            // Educação
            $table->string('escolaridade', 80)->nullable()->after('observacoes');
            $table->longText('formacao_json')->nullable()->after('escolaridade');

            // Bancário
            $table->string('banco', 100)->nullable()->after('formacao_json');
            $table->string('agencia', 20)->nullable()->after('banco');
            $table->string('conta', 20)->nullable()->after('agencia');
            $table->string('conta_digito', 5)->nullable()->after('conta');
            $table->string('pix', 100)->nullable()->after('conta_digito');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('funcionarios')) {
            return;
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            foreach (['pix', 'conta_digito', 'conta', 'agencia', 'banco', 'formacao_json', 'escolaridade'] as $col) {
                if (Schema::hasColumn('funcionarios', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

