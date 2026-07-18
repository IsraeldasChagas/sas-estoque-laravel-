<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return;
        }
        Schema::table('reservas_mesas', function (Blueprint $table) {
            if (! Schema::hasColumn('reservas_mesas', 'pagamentos_conta')) {
                $table->json('pagamentos_conta')->nullable()->after('conta_paga_em');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return;
        }
        Schema::table('reservas_mesas', function (Blueprint $table) {
            if (Schema::hasColumn('reservas_mesas', 'pagamentos_conta')) {
                $table->dropColumn('pagamentos_conta');
            }
        });
    }
};
