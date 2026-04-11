<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_tecnicas', function (Blueprint $table) {
            $table->id();
            $table->string('nome_prato', 500);
            $table->string('tempo_preparo', 255)->nullable();
            $table->string('responsavel_tecnico', 500)->nullable();
            $table->longText('foto_base64')->nullable();
            $table->decimal('preco_prato', 14, 4)->nullable();
            $table->decimal('sugestao_venda', 14, 4)->nullable();
            $table->longText('modo_preparo')->nullable();
            $table->longText('ingredientes_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_tecnicas');
    }
};
