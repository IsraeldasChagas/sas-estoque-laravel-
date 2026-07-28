<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_produtos') && ! Schema::hasColumn('dlv_produtos', 'visivel_pdv')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->boolean('visivel_pdv')->default(true)->after('visivel_loja');
            });
        }

        if (Schema::hasTable('pdv_comanda_itens') && ! Schema::hasColumn('pdv_comanda_itens', 'cardapio_produto_id')) {
            Schema::table('pdv_comanda_itens', function (Blueprint $table) {
                $table->unsignedBigInteger('cardapio_produto_id')->nullable()->after('produto_id');
                $table->index('cardapio_produto_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pdv_comanda_itens') && Schema::hasColumn('pdv_comanda_itens', 'cardapio_produto_id')) {
            Schema::table('pdv_comanda_itens', function (Blueprint $table) {
                $table->dropIndex(['cardapio_produto_id']);
                $table->dropColumn('cardapio_produto_id');
            });
        }

        if (Schema::hasTable('dlv_produtos') && Schema::hasColumn('dlv_produtos', 'visivel_pdv')) {
            Schema::table('dlv_produtos', function (Blueprint $table) {
                $table->dropColumn('visivel_pdv');
            });
        }
    }
};
