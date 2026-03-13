<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funcionarios')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                if (!Schema::hasColumn('funcionarios', 'banco')) {
                    $table->string('banco', 100)->nullable()->after('observacoes');
                }
                if (!Schema::hasColumn('funcionarios', 'agencia')) {
                    $table->string('agencia', 20)->nullable()->after('banco');
                }
                if (!Schema::hasColumn('funcionarios', 'conta')) {
                    $table->string('conta', 20)->nullable()->after('agencia');
                }
                if (!Schema::hasColumn('funcionarios', 'conta_digito')) {
                    $table->string('conta_digito', 5)->nullable()->after('conta');
                }
                if (!Schema::hasColumn('funcionarios', 'pix')) {
                    $table->string('pix', 100)->nullable()->after('conta_digito');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('funcionarios')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                $columns = ['banco', 'agencia', 'conta', 'conta_digito', 'pix'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('funcionarios', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
