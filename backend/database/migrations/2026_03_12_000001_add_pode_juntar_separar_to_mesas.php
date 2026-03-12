<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->boolean('pode_juntar')->default(false)->after('localizacao');
            $table->boolean('pode_separar')->default(false)->after('pode_juntar');
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropColumn(['pode_juntar', 'pode_separar']);
        });
    }
};
