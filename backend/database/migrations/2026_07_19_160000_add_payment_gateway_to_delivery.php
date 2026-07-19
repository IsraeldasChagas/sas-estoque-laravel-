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
                if (! Schema::hasColumn('dlv_loja_config', 'pix_modo')) {
                    $table->string('pix_modo', 20)->default('manual')->after('exigir_pix_confirmado');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pagamento_gateway')) {
                    $table->string('pagamento_gateway', 40)->nullable()->after('pix_modo');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pagamento_gateway_token')) {
                    $table->text('pagamento_gateway_token')->nullable()->after('pagamento_gateway');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pagamento_gateway_public_key')) {
                    $table->string('pagamento_gateway_public_key', 255)->nullable()->after('pagamento_gateway_token');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pagamento_gateway_webhook_secret')) {
                    $table->string('pagamento_gateway_webhook_secret', 255)->nullable()->after('pagamento_gateway_public_key');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pagamento_gateway_sandbox')) {
                    $table->boolean('pagamento_gateway_sandbox')->default(true)->after('pagamento_gateway_webhook_secret');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pagamento_online_ativo')) {
                    $table->boolean('pagamento_online_ativo')->default(false)->after('pagamento_gateway_sandbox');
                }
                if (! Schema::hasColumn('dlv_loja_config', 'pix_expiracao_minutos')) {
                    $table->unsignedSmallInteger('pix_expiracao_minutos')->default(30)->after('pagamento_online_ativo');
                }
            });
        }

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_externo_id')) {
                    $table->string('pagamento_externo_id', 80)->nullable()->after('pagamento_confirmado_em');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_externo_provedor')) {
                    $table->string('pagamento_externo_provedor', 40)->nullable()->after('pagamento_externo_id');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_pix_payload')) {
                    $table->text('pagamento_pix_payload')->nullable()->after('pagamento_externo_provedor');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_pix_expira_em')) {
                    $table->timestamp('pagamento_pix_expira_em')->nullable()->after('pagamento_pix_payload');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_confirmado_origem')) {
                    $table->string('pagamento_confirmado_origem', 20)->nullable()->after('pagamento_pix_expira_em');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'pagamento_gateway_status')) {
                    $table->string('pagamento_gateway_status', 30)->nullable()->after('pagamento_confirmado_origem');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dlv_loja_config')) {
            Schema::table('dlv_loja_config', function (Blueprint $table) {
                foreach ([
                    'pix_modo', 'pagamento_gateway', 'pagamento_gateway_token',
                    'pagamento_gateway_public_key', 'pagamento_gateway_webhook_secret',
                    'pagamento_gateway_sandbox', 'pagamento_online_ativo', 'pix_expiracao_minutos',
                ] as $col) {
                    if (Schema::hasColumn('dlv_loja_config', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                foreach ([
                    'pagamento_externo_id', 'pagamento_externo_provedor', 'pagamento_pix_payload',
                    'pagamento_pix_expira_em', 'pagamento_confirmado_origem', 'pagamento_gateway_status',
                ] as $col) {
                    if (Schema::hasColumn('dlv_pedidos', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
