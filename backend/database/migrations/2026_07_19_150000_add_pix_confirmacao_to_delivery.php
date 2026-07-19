<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_loja_config')) {
            Schema::table('dlv_loja_config', function (Blueprint $table) {
                if (! Schema::hasColumn('dlv_loja_config', 'exigir_pix_confirmado')) {
                    $table->boolean('exigir_pix_confirmado')->default(false)->after('confirmar_pedidos');
                }
            });
        }

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_confirmado_em')) {
                    $table->timestamp('pagamento_confirmado_em')->nullable()->after('pagamento_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_loja_config') && Schema::hasColumn('dlv_loja_config', 'exigir_pix_confirmado')) {
            Schema::table('dlv_loja_config', function (Blueprint $table) {
                $table->dropColumn('exigir_pix_confirmado');
            });
        }

        if (Schema::hasTable('dlv_pedidos') && Schema::hasColumn('dlv_pedidos', 'pagamento_confirmado_em')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                $table->dropColumn('pagamento_confirmado_em');
            });
        }
    }
};
