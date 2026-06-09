<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function temaPadraoJson(): string
    {
        return json_encode([
            'menuBg' => '#070403',
            'menuAccent' => '#de4309',
            'topbarBg' => '#ffffff',
            'pageBg' => '#f6f7fb',
            'pagePrimary' => '#0047ab',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function up(): void
    {
        if (Schema::hasTable('usuarios') && ! Schema::hasColumn('usuarios', 'tema_cores')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->text('tema_cores')->nullable()->after('permissoes_menu');
            });
        }

        if (Schema::hasTable('sistema_configuracoes')) {
            $exists = DB::table('sistema_configuracoes')->where('chave', 'tema_cores_global')->exists();
            if (! $exists) {
                $now = now();
                DB::table('sistema_configuracoes')->insert([
                    'chave' => 'tema_cores_global',
                    'valor' => $this->temaPadraoJson(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'tema_cores')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('tema_cores');
            });
        }

        if (Schema::hasTable('sistema_configuracoes')) {
            DB::table('sistema_configuracoes')->where('chave', 'tema_cores_global')->delete();
        }
    }
};
