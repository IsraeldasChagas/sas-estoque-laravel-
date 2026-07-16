<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ayla_usuarios_autorizados')) {
            Schema::table('ayla_usuarios_autorizados', function (Blueprint $table) {
                if (! Schema::hasColumn('ayla_usuarios_autorizados', 'telefone_telegram')) {
                    $table->string('telefone_telegram', 20)->nullable()->after('telegram_nome');
                }
                if (! Schema::hasColumn('ayla_usuarios_autorizados', 'telegram_vinculado_em')) {
                    $table->timestamp('telegram_vinculado_em')->nullable()->after('telefone_telegram');
                }
                if (! Schema::hasColumn('ayla_usuarios_autorizados', 'telegram_sincronizado_em')) {
                    $table->timestamp('telegram_sincronizado_em')->nullable()->after('telegram_vinculado_em');
                }
                if (! Schema::hasColumn('ayla_usuarios_autorizados', 'telegram_sync_status')) {
                    $table->string('telegram_sync_status', 30)->nullable()->after('telegram_sincronizado_em');
                }
                if (! Schema::hasColumn('ayla_usuarios_autorizados', 'telegram_sync_erro')) {
                    $table->text('telegram_sync_erro')->nullable()->after('telegram_sync_status');
                }
            });
        }

        if (! Schema::hasTable('ayla_convites')) {
            Schema::create('ayla_convites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ayla_usuario_autorizado_id');
                $table->unsignedBigInteger('usuario_id');
                $table->string('token_hash', 128);
                $table->string('status', 20)->default('pendente');
                $table->timestamp('expira_em');
                $table->timestamp('usado_em')->nullable();
                $table->timestamp('cancelado_em')->nullable();
                $table->string('telegram_user_id', 60)->nullable();
                $table->string('telegram_username', 120)->nullable();
                $table->string('telegram_nome', 160)->nullable();
                $table->string('telefone_telegram', 20)->nullable();
                $table->unsignedBigInteger('criado_por')->nullable();
                $table->timestamps();

                $table->index(['ayla_usuario_autorizado_id', 'status']);
                $table->index(['token_hash', 'status']);
                $table->index('expira_em');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ayla_convites');

        if (Schema::hasTable('ayla_usuarios_autorizados')) {
            Schema::table('ayla_usuarios_autorizados', function (Blueprint $table) {
                foreach ([
                    'telefone_telegram',
                    'telegram_vinculado_em',
                    'telegram_sincronizado_em',
                    'telegram_sync_status',
                    'telegram_sync_erro',
                ] as $col) {
                    if (Schema::hasColumn('ayla_usuarios_autorizados', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
