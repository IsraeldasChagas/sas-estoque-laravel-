<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas_mesas', function (Blueprint $table) {
            $table->string('local', 100)->nullable()->after('observacao');
            $table->string('ocasiao', 255)->nullable()->after('local');
        });
    }

    public function down(): void
    {
        Schema::table('reservas_mesas', function (Blueprint $table) {
            $table->dropColumn(['local', 'ocasiao']);
        });
    }
};
