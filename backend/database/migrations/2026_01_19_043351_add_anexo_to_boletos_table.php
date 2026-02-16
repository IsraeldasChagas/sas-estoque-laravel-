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
            $table->string('anexo_path')->nullable()->after('observacoes');
            $table->string('anexo_nome')->nullable()->after('anexo_path');
            $table->string('anexo_tipo')->nullable()->after('anexo_nome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->dropColumn(['anexo_path', 'anexo_nome', 'anexo_tipo']);
        });
    }
};
