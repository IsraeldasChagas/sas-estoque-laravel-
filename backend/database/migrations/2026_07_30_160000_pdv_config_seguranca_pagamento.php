<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_configuracoes')) {
            Schema::create('pdv_configuracoes', function (Blueprint $table) {
                $table->id();
                $table->boolean('exigir_nsu_cartao')->default(false);
                $table->boolean('exigir_autorizacao_cartao')->default(false);
                $table->boolean('exigir_bandeira_cartao')->default(false);
                $table->boolean('exigir_identificador_pix')->default(false);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });

            DB::table('pdv_configuracoes')->insert([
                'exigir_nsu_cartao' => false,
                'exigir_autorizacao_cartao' => false,
                'exigir_bandeira_cartao' => false,
                'exigir_identificador_pix' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                if (! Schema::hasColumn('vendas', 'pagamento_nsu')) {
                    $table->string('pagamento_nsu', 32)->nullable()->after('forma_pagamento');
                }
                if (! Schema::hasColumn('vendas', 'pagamento_autorizacao')) {
                    $table->string('pagamento_autorizacao', 32)->nullable()->after('pagamento_nsu');
                }
                if (! Schema::hasColumn('vendas', 'pagamento_bandeira')) {
                    $table->string('pagamento_bandeira', 40)->nullable()->after('pagamento_autorizacao');
                }
                if (! Schema::hasColumn('vendas', 'pagamento_parcelas')) {
                    $table->unsignedTinyInteger('pagamento_parcelas')->nullable()->after('pagamento_bandeira');
                }
                if (! Schema::hasColumn('vendas', 'pagamento_pix_id')) {
                    $table->string('pagamento_pix_id', 120)->nullable()->after('pagamento_parcelas');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendas')) {
            Schema::table('vendas', function (Blueprint $table) {
                foreach (['pagamento_pix_id', 'pagamento_parcelas', 'pagamento_bandeira', 'pagamento_autorizacao', 'pagamento_nsu'] as $col) {
                    if (Schema::hasColumn('vendas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('pdv_configuracoes');
    }
};
