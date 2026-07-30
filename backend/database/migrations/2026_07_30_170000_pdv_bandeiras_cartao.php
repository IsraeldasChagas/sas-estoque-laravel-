<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_bandeiras_cartao')) {
            Schema::create('pdv_bandeiras_cartao', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 40);
                $table->boolean('ativo')->default(true);
                $table->unsignedSmallInteger('ordem')->default(0);
                $table->timestamps();
                $table->unique('nome');
            });

            $now = now();
            $ordem = 0;
            foreach (['Visa', 'Mastercard', 'Elo', 'American Express', 'Hipercard', 'Cabal'] as $nome) {
                DB::table('pdv_bandeiras_cartao')->insert([
                    'nome' => $nome,
                    'ativo' => true,
                    'ordem' => ++$ordem,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_bandeiras_cartao');
    }
};
