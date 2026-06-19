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
            if (! Schema::hasColumn('funcionarios', 'tipo_vinculo')) {
                $table->string('tipo_vinculo', 40)->nullable()->after('cargo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('funcionarios') || ! Schema::hasColumn('funcionarios', 'tipo_vinculo')) {
            return;
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropColumn('tipo_vinculo');
        });
    }
};
