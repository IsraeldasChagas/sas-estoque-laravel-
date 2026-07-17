<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dlv_pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('dlv_pedidos', 'cliente_email')) {
                $table->string('cliente_email', 190)->nullable()->after('cliente_whatsapp');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'cliente_token')) {
                $table->string('cliente_token', 64)->nullable()->index()->after('cliente_email');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'estoque_baixado_em')) {
                $table->timestamp('estoque_baixado_em')->nullable()->after('observacoes');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'estoque_restaurado_em')) {
                $table->timestamp('estoque_restaurado_em')->nullable()->after('estoque_baixado_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dlv_pedidos', function (Blueprint $table) {
            foreach (['cliente_email', 'cliente_token', 'estoque_baixado_em', 'estoque_restaurado_em'] as $column) {
                if (Schema::hasColumn('dlv_pedidos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
