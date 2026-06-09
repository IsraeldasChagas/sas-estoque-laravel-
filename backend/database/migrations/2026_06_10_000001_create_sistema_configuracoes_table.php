<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sistema_configuracoes')) {
            return;
        }

        Schema::create('sistema_configuracoes', function (Blueprint $table) {
            $table->string('chave', 80)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach ([
            'empresa_nome' => 'Grupo Sabor Paraense',
            'empresa_cnpj' => '',
            'empresa_email' => '',
            'empresa_telefone' => '',
            'empresa_endereco' => '',
            'suporte_email' => '',
            'observacoes_sistema' => '',
        ] as $chave => $valor) {
            DB::table('sistema_configuracoes')->insert([
                'chave' => $chave,
                'valor' => $valor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sistema_configuracoes');
    }
};
