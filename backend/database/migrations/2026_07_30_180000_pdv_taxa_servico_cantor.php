<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pdv_configuracoes')) {
            Schema::table('pdv_configuracoes', function (Blueprint $table) {
                if (! Schema::hasColumn('pdv_configuracoes', 'taxa_servico_ativa')) {
                    $table->boolean('taxa_servico_ativa')->default(false)->after('exigir_identificador_pix');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'taxa_servico_modo')) {
                    $table->string('taxa_servico_modo', 16)->default('percentual')->after('taxa_servico_ativa');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'taxa_servico_valor')) {
                    $table->decimal('taxa_servico_valor', 10, 2)->default(10)->after('taxa_servico_modo');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'taxa_servico_padrao_mesa')) {
                    $table->boolean('taxa_servico_padrao_mesa')->default(true)->after('taxa_servico_valor');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'taxa_servico_padrao_balcao')) {
                    $table->boolean('taxa_servico_padrao_balcao')->default(false)->after('taxa_servico_padrao_mesa');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'pagamento_cantor_ativo')) {
                    $table->boolean('pagamento_cantor_ativo')->default(false)->after('taxa_servico_padrao_balcao');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'pagamento_cantor_modo')) {
                    $table->string('pagamento_cantor_modo', 16)->default('percentual')->after('pagamento_cantor_ativo');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'pagamento_cantor_valor')) {
                    $table->decimal('pagamento_cantor_valor', 10, 2)->default(0)->after('pagamento_cantor_modo');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'pagamento_cantor_padrao_mesa')) {
                    $table->boolean('pagamento_cantor_padrao_mesa')->default(false)->after('pagamento_cantor_valor');
                }
                if (! Schema::hasColumn('pdv_configuracoes', 'pagamento_cantor_padrao_balcao')) {
                    $table->boolean('pagamento_cantor_padrao_balcao')->default(false)->after('pagamento_cantor_padrao_mesa');
                }
            });
        }

        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                if (! Schema::hasColumn('vendas', 'taxa_servico')) {
                    $table->decimal('taxa_servico', 14, 2)->default(0)->after('desconto');
                }
                if (! Schema::hasColumn('vendas', 'pagamento_cantor')) {
                    $table->decimal('pagamento_cantor', 14, 2)->default(0)->after('taxa_servico');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                foreach (['pagamento_cantor', 'taxa_servico'] as $col) {
                    if (Schema::hasColumn('vendas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('pdv_configuracoes')) {
            Schema::table('pdv_configuracoes', function (Blueprint $table) {
                foreach ([
                    'pagamento_cantor_padrao_balcao',
                    'pagamento_cantor_padrao_mesa',
                    'pagamento_cantor_valor',
                    'pagamento_cantor_modo',
                    'pagamento_cantor_ativo',
                    'taxa_servico_padrao_balcao',
                    'taxa_servico_padrao_mesa',
                    'taxa_servico_valor',
                    'taxa_servico_modo',
                    'taxa_servico_ativa',
                ] as $col) {
                    if (Schema::hasColumn('pdv_configuracoes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
