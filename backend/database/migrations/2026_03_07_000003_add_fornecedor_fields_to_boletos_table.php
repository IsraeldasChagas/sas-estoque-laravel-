<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona fornecedor_id e campos de histórico (nome_fornecedor, cnpj_fornecedor)
     * para manter o histórico legível mesmo após exclusão do fornecedor.
     */
    public function up(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            if (!Schema::hasColumn('boletos', 'fornecedor_id')) {
                $table->unsignedBigInteger('fornecedor_id')->nullable()->after('unidade_id');
            }
            if (!Schema::hasColumn('boletos', 'nome_fornecedor')) {
                $table->string('nome_fornecedor')->nullable()->after('fornecedor');
            }
            if (!Schema::hasColumn('boletos', 'cnpj_fornecedor')) {
                $table->string('cnpj_fornecedor', 18)->nullable()->after('nome_fornecedor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            if (Schema::hasColumn('boletos', 'fornecedor_id')) {
                $table->dropColumn('fornecedor_id');
            }
            if (Schema::hasColumn('boletos', 'nome_fornecedor')) {
                $table->dropColumn('nome_fornecedor');
            }
            if (Schema::hasColumn('boletos', 'cnpj_fornecedor')) {
                $table->dropColumn('cnpj_fornecedor');
            }
        });
    }
};
