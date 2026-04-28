<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rh_entrevistas')) {
            Schema::create('rh_entrevistas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('candidato_id');
                $table->date('data')->nullable();
                $table->time('hora')->nullable();
                $table->string('local', 160)->nullable();
                $table->string('responsavel', 160)->nullable();
                $table->longText('observacao')->nullable();
                $table->string('status', 30)->default('agendada'); // agendada, realizada, cancelada
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

