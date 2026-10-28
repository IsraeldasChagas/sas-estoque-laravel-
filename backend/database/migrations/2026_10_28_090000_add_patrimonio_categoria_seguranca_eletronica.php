<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('patrimonio_categorias')) {
            return;
        }

        $existe = DB::table('patrimonio_categorias')
            ->where('slug', 'seguranca-eletronica')
            ->orWhereRaw('LOWER(nome) IN (?, ?)', ['segurança eletrônica', 'seguranca eletronica'])
            ->exists();
        if ($existe) {
            return;
        }

        $now = now();
        DB::table('patrimonio_categorias')->insert([
            'nome' => 'Segurança Eletrônica',
            'slug' => 'seguranca-eletronica',
            'icone' => '📹',
            'ordem' => 9,
            'ativo' => true,
            'tipo_campos' => 'geral',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('patrimonio_categorias')) {
            return;
        }

        $id = DB::table('patrimonio_categorias')->where('slug', 'seguranca-eletronica')->value('id');
        if (! $id || DB::table('patrimonios')->where('categoria_id', $id)->exists()) {
            return;
        }
        DB::table('patrimonio_categorias')->where('id', $id)->delete();
    }
};
