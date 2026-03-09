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
            $table->string('numero_boleto')->nullable()->after('observacoes');
            $table->string('nome_pagador')->nullable()->after('numero_boleto');
            $table->string('whatsapp_pagador')->nullable()->after('nome_pagador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->dropColumn(['numero_boleto', 'nome_pagador', 'whatsapp_pagador']);
        });
    }
};
