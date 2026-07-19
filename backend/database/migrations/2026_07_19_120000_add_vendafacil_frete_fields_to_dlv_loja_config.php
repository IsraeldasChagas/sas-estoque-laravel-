<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            return;
        }

        Schema::table('dlv_loja_config', function (Blueprint $table) {
            if (! Schema::hasColumn('dlv_loja_config', 'frete_google_rs_por_km')) {
                $table->decimal('frete_google_rs_por_km', 14, 2)->nullable()->after('frete_chuva_ativa');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_google_taxa_minima')) {
                $table->decimal('frete_google_taxa_minima', 14, 2)->nullable()->after('frete_google_rs_por_km');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_google_km_max')) {
                $table->decimal('frete_google_km_max', 10, 2)->nullable()->after('frete_google_taxa_minima');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_origem_endereco')) {
                $table->string('frete_origem_endereco', 500)->nullable()->after('frete_google_km_max');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_entrega_lat_origem')) {
                $table->decimal('frete_entrega_lat_origem', 10, 7)->nullable()->after('frete_origem_endereco');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_entrega_lng_origem')) {
                $table->decimal('frete_entrega_lng_origem', 10, 7)->nullable()->after('frete_entrega_lat_origem');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_km_incluso')) {
                $table->decimal('frete_km_incluso', 10, 2)->nullable()->after('frete_entrega_lng_origem');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'frete_valor_km_extra')) {
                $table->decimal('frete_valor_km_extra', 14, 2)->nullable()->after('frete_km_incluso');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            return;
        }

        Schema::table('dlv_loja_config', function (Blueprint $table) {
            foreach ([
                'frete_google_rs_por_km',
                'frete_google_taxa_minima',
                'frete_google_km_max',
                'frete_origem_endereco',
                'frete_entrega_lat_origem',
                'frete_entrega_lng_origem',
                'frete_km_incluso',
                'frete_valor_km_extra',
            ] as $col) {
                if (Schema::hasColumn('dlv_loja_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
