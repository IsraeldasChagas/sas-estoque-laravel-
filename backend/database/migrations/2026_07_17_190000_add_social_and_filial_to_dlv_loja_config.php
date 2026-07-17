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
            if (! Schema::hasColumn('dlv_loja_config', 'instagram_url')) {
                $table->string('instagram_url', 255)->nullable()->after('endereco_texto');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'facebook_url')) {
                $table->string('facebook_url', 255)->nullable()->after('instagram_url');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'filial_nome')) {
                $table->string('filial_nome', 160)->nullable()->after('facebook_url');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'filial_link_url')) {
                $table->string('filial_link_url', 255)->nullable()->after('filial_nome');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'filial_logo_path')) {
                $table->string('filial_logo_path', 255)->nullable()->after('filial_link_url');
            }
            if (! Schema::hasColumn('dlv_loja_config', 'entrega_texto')) {
                $table->string('entrega_texto', 180)->nullable()->after('filial_logo_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            return;
        }

        Schema::table('dlv_loja_config', function (Blueprint $table) {
            foreach (['instagram_url', 'facebook_url', 'filial_nome', 'filial_link_url', 'filial_logo_path', 'entrega_texto'] as $col) {
                if (Schema::hasColumn('dlv_loja_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
