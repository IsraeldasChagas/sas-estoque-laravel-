<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dlv_entregadores') && ! Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->string('acesso_token', 64)->nullable()->unique()->after('ordem');
            });

            $rows = DB::table('dlv_entregadores')->whereNull('acesso_token')->orWhere('acesso_token', '')->get(['id']);
            foreach ($rows as $row) {
                DB::table('dlv_entregadores')->where('id', $row->id)->update([
                    'acesso_token' => Str::lower(Str::random(48)),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                if (! Schema::hasColumn('dlv_pedidos', 'oferta_status')) {
                    $table->string('oferta_status', 20)->nullable()->index()->after('entregador_token');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'oferta_aberta_em')) {
                    $table->timestamp('oferta_aberta_em')->nullable()->after('oferta_status');
                }
                if (! Schema::hasColumn('dlv_pedidos', 'oferta_aceita_em')) {
                    $table->timestamp('oferta_aceita_em')->nullable()->after('oferta_aberta_em');
                }
            });
        }

        if (! Schema::hasTable('dlv_pedido_oferta_recusas')) {
            Schema::create('dlv_pedido_oferta_recusas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pedido_id')->index();
                $table->unsignedBigInteger('entregador_id')->index();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['pedido_id', 'entregador_id'], 'dlv_oferta_recusa_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dlv_pedido_oferta_recusas');

        if (Schema::hasTable('dlv_pedidos')) {
            Schema::table('dlv_pedidos', function (Blueprint $table) {
                foreach (['oferta_aceita_em', 'oferta_aberta_em', 'oferta_status'] as $col) {
                    if (Schema::hasColumn('dlv_pedidos', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('dlv_entregadores') && Schema::hasColumn('dlv_entregadores', 'acesso_token')) {
            Schema::table('dlv_entregadores', function (Blueprint $table) {
                $table->dropColumn('acesso_token');
            });
        }
    }
};
