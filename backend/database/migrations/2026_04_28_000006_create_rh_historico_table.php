<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_historico')) {
            Schema::create('rh_historico', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('candidato_id');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('status_antigo', 30)->nullable();
                $table->string('status_novo', 30);
                $table->timestamp('data')->useCurrent();
                $table->timestamps();

                $table->index(['candidato_id', 'data']);
            });
        }
    }

    public function down(): void
    {
        // Sem down destrutivo por segurança.
    }
};

