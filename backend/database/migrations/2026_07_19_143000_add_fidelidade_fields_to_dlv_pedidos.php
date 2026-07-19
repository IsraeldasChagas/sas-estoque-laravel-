<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_pedidos')) {
            return;
        }

        Schema::table('dlv_pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('dlv_pedidos', 'participa_fidelidade')) {
                $table->boolean('participa_fidelidade')->default(false)->after('observacoes');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'fidelidade_nome')) {
                $table->string('fidelidade_nome', 160)->nullable()->after('participa_fidelidade');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'fidelidade_cpf')) {
                $table->string('fidelidade_cpf', 11)->nullable()->after('fidelidade_nome');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'fidelidade_email')) {
                $table->string('fidelidade_email', 160)->nullable()->after('fidelidade_cpf');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'fidelidade_whatsapp')) {
                $table->string('fidelidade_whatsapp', 30)->nullable()->after('fidelidade_email');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'fidelidade_lgpd_aceite_em')) {
                $table->timestamp('fidelidade_lgpd_aceite_em')->nullable()->after('fidelidade_whatsapp');
            }
            if (! Schema::hasColumn('dlv_pedidos', 'fidelidade_selo_creditado_em')) {
                $table->timestamp('fidelidade_selo_creditado_em')->nullable()->after('fidelidade_lgpd_aceite_em');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlv_pedidos')) {
            return;
        }

        Schema::table('dlv_pedidos', function (Blueprint $table) {
            foreach ([
                'fidelidade_selo_creditado_em',
                'fidelidade_lgpd_aceite_em',
                'fidelidade_whatsapp',
                'fidelidade_email',
                'fidelidade_cpf',
                'fidelidade_nome',
                'participa_fidelidade',
            ] as $column) {
                if (Schema::hasColumn('dlv_pedidos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
