<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona campos de fornecedor em lotes e movimentacoes para histórico.
     */
    public function up(): void
    {
        if (Schema::hasTable('lotes')) {
            Schema::table('lotes', function (Blueprint $table) {
                if (!Schema::hasColumn('lotes', 'fornecedor_id')) {
                    $table->unsignedBigInteger('fornecedor_id')->nullable()->after('local_id');
                }
                if (!Schema::hasColumn('lotes', 'nome_fornecedor')) {
                    $table->string('nome_fornecedor')->nullable()->after('fornecedor_id');
                }
                if (!Schema::hasColumn('lotes', 'cnpj_fornecedor')) {
                    $table->string('cnpj_fornecedor', 18)->nullable()->after('nome_fornecedor');
                }
            });
        }

        if (Schema::hasTable('movimentacoes')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                if (!Schema::hasColumn('movimentacoes', 'fornecedor_id')) {
                    $table->unsignedBigInteger('fornecedor_id')->nullable();
                }
                if (!Schema::hasColumn('movimentacoes', 'nome_fornecedor')) {
                    $table->string('nome_fornecedor')->nullable()->after('fornecedor_id');
                }
                if (!Schema::hasColumn('movimentacoes', 'cnpj_fornecedor')) {
                    $table->string('cnpj_fornecedor', 18)->nullable()->after('nome_fornecedor');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lotes')) {
            Schema::table('lotes', function (Blueprint $table) {
                if (Schema::hasColumn('lotes', 'fornecedor_id')) {
                    $table->dropColumn('fornecedor_id');
                }
                if (Schema::hasColumn('lotes', 'nome_fornecedor')) {
                    $table->dropColumn('nome_fornecedor');
                }
                if (Schema::hasColumn('lotes', 'cnpj_fornecedor')) {
                    $table->dropColumn('cnpj_fornecedor');
                }
            });
        }

        if (Schema::hasTable('movimentacoes')) {
            Schema::table('movimentacoes', function (Blueprint $table) {
                if (Schema::hasColumn('movimentacoes', 'fornecedor_id')) {
                    $table->dropColumn('fornecedor_id');
                }
                if (Schema::hasColumn('movimentacoes', 'nome_fornecedor')) {
                    $table->dropColumn('nome_fornecedor');
                }
                if (Schema::hasColumn('movimentacoes', 'cnpj_fornecedor')) {
                    $table->dropColumn('cnpj_fornecedor');
                }
            });
        }
    }
};
