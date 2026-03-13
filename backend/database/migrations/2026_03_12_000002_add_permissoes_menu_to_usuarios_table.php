<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuarios') && !Schema::hasColumn('usuarios', 'permissoes_menu')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->json('permissoes_menu')->nullable()->after('perfil');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'permissoes_menu')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('permissoes_menu');
            });
        }
    }
};
