<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_curriculos')) {
            Schema::create('rh_curriculos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('candidato_id');
                $table->string('arquivo_path', 255);
                $table->string('arquivo_nome_original', 255)->nullable();
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('tamanho_bytes')->nullable();
                $table->timestamps();

                $table->index(['candidato_id']);
            });
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};

