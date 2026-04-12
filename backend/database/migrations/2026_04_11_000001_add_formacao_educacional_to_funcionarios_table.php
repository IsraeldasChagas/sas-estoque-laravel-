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
            if (! Schema::hasColumn('funcionarios', 'escolaridade')) {
                $table->string('escolaridade', 80)->nullable()->after('observacoes');
            }
            if (! Schema::hasColumn('funcionarios', 'formacao_json')) {
                $table->longText('formacao_json')->nullable()->after('escolaridade');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('funcionarios')) {
            return;
        }
        Schema::table('funcionarios', function (Blueprint $table) {
            foreach (['formacao_json', 'escolaridade'] as $col) {
                if (Schema::hasColumn('funcionarios', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
