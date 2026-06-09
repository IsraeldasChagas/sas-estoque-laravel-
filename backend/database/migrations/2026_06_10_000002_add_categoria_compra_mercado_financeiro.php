<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financeiro_categorias')) {
            return;
        }

        $existe = DB::table('financeiro_categorias')->where('nome', 'Compra de mercado')->exists();
        if ($existe) {
            return;
        }

        $ordemFornecedores = DB::table('financeiro_categorias')->where('nome', 'Fornecedores')->value('ordem');
        $ordem = $ordemFornecedores !== null ? ((int) $ordemFornecedores + 1) : 4;
        $now = now();

        DB::table('financeiro_categorias')
            ->where('ordem', '>=', $ordem)
            ->increment('ordem');

        DB::table('financeiro_categorias')->insert([
            'nome' => 'Compra de mercado',
            'tipo' => 'saida',
            'ativo' => true,
            'ordem' => $ordem,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('financeiro_categorias')) {
            return;
        }
        DB::table('financeiro_categorias')->where('nome', 'Compra de mercado')->delete();
    }
};
