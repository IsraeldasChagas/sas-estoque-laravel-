<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('despesas_fixas_categorias')) {
            return;
        }
        Schema::create('despesas_fixas_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestamps();
            $table->index('ordem');
            $table->index('ativo');
        });

        $now = now();
        $seed = [
            ['nome' => 'Aluguel', 'ordem' => 10],
            ['nome' => 'Energia', 'ordem' => 20],
            ['nome' => 'Água / esgoto', 'ordem' => 30],
            ['nome' => 'Internet / telefonia', 'ordem' => 40],
            ['nome' => 'Salários e encargos', 'ordem' => 50],
            ['nome' => 'Impostos e taxas', 'ordem' => 60],
            ['nome' => 'Manutenção', 'ordem' => 70],
            ['nome' => 'Marketing', 'ordem' => 80],
            ['nome' => 'Outro', 'ordem' => 90],
        ];
        foreach ($seed as $row) {
            DB::table('despesas_fixas_categorias')->insert([
                'nome' => $row['nome'],
                'ordem' => $row['ordem'],
                'ativo' => true,
                'criado_por' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas_fixas_categorias');
    }
};
