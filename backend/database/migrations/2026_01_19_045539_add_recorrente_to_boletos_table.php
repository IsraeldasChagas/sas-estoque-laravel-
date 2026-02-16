<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->boolean('is_recorrente')->default(false)->after('observacoes');
            $table->integer('meses_recorrencia')->nullable()->after('is_recorrente');
            $table->string('grupo_recorrencia')->nullable()->after('meses_recorrencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->dropColumn(['is_recorrente', 'meses_recorrencia', 'grupo_recorrencia']);
        });
    }
};
