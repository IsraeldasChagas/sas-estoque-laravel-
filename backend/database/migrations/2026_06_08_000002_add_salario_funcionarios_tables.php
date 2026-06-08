<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funcionarios') && ! Schema::hasColumn('funcionarios', 'salario_base')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                $table->decimal('salario_base', 12, 2)->nullable()->after('cargo');
            });
        }

        if (! Schema::hasTable('funcionarios_salarios')) {
            Schema::create('funcionarios_salarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('funcionario_id');
                $table->decimal('valor', 12, 2);
                $table->date('vigencia_inicio')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamps();
                $table->index('funcionario_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('funcionarios_salarios')) {
            Schema::dropIfExists('funcionarios_salarios');
        }
        if (Schema::hasTable('funcionarios') && Schema::hasColumn('funcionarios', 'salario_base')) {
            Schema::table('funcionarios', function (Blueprint $table) {
                $table->dropColumn('salario_base');
            });
        }
    }
};
