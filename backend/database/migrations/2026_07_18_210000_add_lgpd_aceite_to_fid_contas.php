<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_contas')) {
            return;
        }

        Schema::table('fid_contas', function (Blueprint $table) {
            if (! Schema::hasColumn('fid_contas', 'lgpd_aceite_em')) {
                $table->timestamp('lgpd_aceite_em')->nullable()->after('origem_id');
            }
            if (! Schema::hasColumn('fid_contas', 'lgpd_aceite_versao')) {
                $table->string('lgpd_aceite_versao', 20)->nullable()->after('lgpd_aceite_em');
            }
            if (! Schema::hasColumn('fid_contas', 'lgpd_aceite_ip')) {
                $table->string('lgpd_aceite_ip', 45)->nullable()->after('lgpd_aceite_versao');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fid_contas')) {
            return;
        }

        Schema::table('fid_contas', function (Blueprint $table) {
            $cols = [];
            foreach (['lgpd_aceite_em', 'lgpd_aceite_versao', 'lgpd_aceite_ip'] as $col) {
                if (Schema::hasColumn('fid_contas', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
