<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dlv_entregadores')) {
            return;
        }

        Schema::table('dlv_entregadores', function (Blueprint $table) {
            if (! Schema::hasColumn('dlv_entregadores', 'acesso_install_id')) {
                $table->string('acesso_install_id', 64)->nullable()->after('acesso_pin_usado_em');
            }
            if (! Schema::hasColumn('dlv_entregadores', 'recebendo_entregas')) {
                $table->boolean('recebendo_entregas')->default(true)->after('acesso_install_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dlv_entregadores')) {
            return;
        }

        Schema::table('dlv_entregadores', function (Blueprint $table) {
            foreach (['recebendo_entregas', 'acesso_install_id'] as $col) {
                if (Schema::hasColumn('dlv_entregadores', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
