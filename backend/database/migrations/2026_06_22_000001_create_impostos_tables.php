<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('impostos')) {
            Schema::create('impostos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unidade_id')->nullable();
                $table->unsignedBigInteger('boleto_id')->nullable();
                $table->string('tipo_imposto', 40)->default('OUTROS');
                $table->string('descricao');
                $table->string('orgao', 255)->nullable();
                $table->string('competencia', 7)->nullable();
                $table->string('numero_documento', 120)->nullable();
                $table->date('data_vencimento');
                $table->decimal('valor', 12, 2);
                $table->string('status', 20)->default('A_VENCER');
                $table->text('observacoes')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->index(['unidade_id', 'data_vencimento']);
                $table->index('status');
                $table->index('boleto_id');
            });
        }

        if (! Schema::hasTable('imposto_anexos')) {
            Schema::create('imposto_anexos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('imposto_id');
                $table->string('tipo', 20)->default('guia');
                $table->string('path');
                $table->string('nome');
                $table->string('tipo_arquivo', 20)->nullable();
                $table->timestamps();

                $table->index(['imposto_id', 'tipo']);
            });
        }

        if (Schema::hasTable('boletos') && ! Schema::hasColumn('boletos', 'imposto_id')) {
            Schema::table('boletos', function (Blueprint $table) {
                $table->unsignedBigInteger('imposto_id')->nullable()->after('usuario_id');
                $table->index('imposto_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('boletos') && Schema::hasColumn('boletos', 'imposto_id')) {
            Schema::table('boletos', function (Blueprint $table) {
                $table->dropIndex(['imposto_id']);
                $table->dropColumn('imposto_id');
            });
        }
        Schema::dropIfExists('imposto_anexos');
        Schema::dropIfExists('impostos');
    }
};
