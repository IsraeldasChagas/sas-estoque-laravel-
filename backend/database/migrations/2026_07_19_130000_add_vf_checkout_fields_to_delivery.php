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
                if (! Schema::hasColumn('dlv_loja_config', 'pix_instrucoes')) {
                    $table->text('pix_instrucoes')->nullable()->after('pix_beneficiario');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pix_copia_cola')) {
                    $table->text('pix_copia_cola')->nullable()->after('pix_instrucoes');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pix_banco')) {
                    $table->string('pix_banco', 120)->nullable()->after('pix_copia_cola');
                }
            });
        }

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_troco_para')) {
                    $table->decimal('pagamento_troco_para', 14, 2)->nullable()->after('pagamento_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_loja_config')) {
            Schema::table('dlv_loja_config', function (Blueprint $table) {
                foreach (['pix_instrucoes', 'pix_copia_cola', 'pix_banco'] as $col) {
                    if (Schema::hasColumn('dlv_loja_config', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                if (Schema::hasColumn('dlv_pedidos', 'pagamento_troco_para')) {
                    $table->dropColumn('pagamento_troco_para');
                }
            });
        }
    }
};
