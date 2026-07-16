<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integrations')) {
            Schema::table('integrations', function (Blueprint $table) {
                if (! Schema::hasColumn('integrations', 'integration_status')) {
                    $table->string('integration_status', 40)->default('not_configured')->after('connection_status');
                }
                if (! Schema::hasColumn('integrations', 'last_successful_connection_at')) {
                    $table->timestamp('last_successful_connection_at')->nullable()->after('last_sync_at');
                }
                if (! Schema::hasColumn('integrations', 'consecutive_failures')) {
                    $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_error');
                }
                if (! Schema::hasColumn('integrations', 'empresa_external_name')) {
                    $table->string('empresa_external_name', 255)->nullable()->after('empresa_external_id');
                }
                if (! Schema::hasColumn('integrations', 'observacoes')) {
                    $table->text('observacoes')->nullable()->after('config_json');
                }
            });
        }

        if (Schema::hasTable('integration_logs')) {
            Schema::table('integration_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('integration_logs', 'operation')) {
                    $table->string('operation', 80)->nullable()->after('provider');
                }
                if (! Schema::hasColumn('integration_logs', 'attempt_number')) {
                    $table->unsignedTinyInteger('attempt_number')->nullable()->after('response_time_ms');
                }
                if (! Schema::hasColumn('integration_logs', 'ip')) {
                    $table->string('ip', 45)->nullable()->after('usuario_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('integrations')) {
            Schema::table('integrations', function (Blueprint $table) {
                foreach (['integration_status', 'last_successful_connection_at', 'consecutive_failures', 'empresa_external_name', 'observacoes'] as $col) {
                    if (Schema::hasColumn('integrations', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('integration_logs')) {
            Schema::table('integration_logs', function (Blueprint $table) {
                foreach (['operation', 'attempt_number', 'ip'] as $col) {
                    if (Schema::hasColumn('integration_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
