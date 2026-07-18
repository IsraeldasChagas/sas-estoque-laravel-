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
            if (! Schema::hasColumn('reservas_mesas', 'conta_paga')) {
                $table->boolean('conta_paga')->default(false)->after('status');
            }
            if (! Schema::hasColumn('reservas_mesas', 'valor_conta')) {
                $table->decimal('valor_conta', 12, 2)->nullable()->after('conta_paga');
            }
            if (! Schema::hasColumn('reservas_mesas', 'conta_paga_em')) {
                $table->timestamp('conta_paga_em')->nullable()->after('valor_conta');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return;
        }
        Schema::table('reservas_mesas', function (Blueprint $table) {
            foreach (['conta_paga_em', 'valor_conta', 'conta_paga'] as $col) {
                if (Schema::hasColumn('reservas_mesas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
