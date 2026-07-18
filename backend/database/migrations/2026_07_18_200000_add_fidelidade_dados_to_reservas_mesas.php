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
            if (! Schema::hasColumn('reservas_mesas', 'fidelidade_nome')) {
                $table->string('fidelidade_nome', 160)->nullable()->after('participa_fidelidade');
            }
            if (! Schema::hasColumn('reservas_mesas', 'fidelidade_cpf')) {
                $table->string('fidelidade_cpf', 11)->nullable()->after('fidelidade_nome');
            }
            if (! Schema::hasColumn('reservas_mesas', 'fidelidade_email')) {
                $table->string('fidelidade_email', 160)->nullable()->after('fidelidade_cpf');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return;
        }

        Schema::table('reservas_mesas', function (Blueprint $table) {
            foreach (['fidelidade_email', 'fidelidade_cpf', 'fidelidade_nome'] as $col) {
                if (Schema::hasColumn('reservas_mesas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
