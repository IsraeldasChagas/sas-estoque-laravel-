<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras_rapidas')) {
            Schema::create('compras_rapidas', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 160);
                $table->unsignedBigInteger('unidade_id')->nullable()->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('status', 20)->default('aberta')->index();
                $table->dateTime('feita_em')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('compras_rapidas_itens')) {
            Schema::create('compras_rapidas_itens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lista_id')->index();
                $table->unsignedBigInteger('produto_id')->nullable();
                $table->string('produto_nome', 200);
                $table->decimal('quantidade', 14, 3);
                $table->string('tipo', 20)->default('UN');
                $table->string('observacao', 255)->nullable();
                $table->unsignedInteger('ordem')->default(0);
            });
        }

        if (Schema::hasTable('unidades') && ! Schema::hasColumn('unidades', 'logo')) {
            Schema::table('unidades', function (Blueprint $table) {
                $table->string('logo', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_rapidas_itens');
        Schema::dropIfExists('compras_rapidas');
        if (Schema::hasTable('unidades') && Schema::hasColumn('unidades', 'logo')) {
            Schema::table('unidades', function (Blueprint $table) {
                $table->dropColumn('logo');
            });
        }
    }
};
