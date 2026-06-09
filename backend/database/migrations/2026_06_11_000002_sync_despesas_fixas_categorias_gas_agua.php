<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('despesas_fixas_categorias')) {
            return;
        }

        $now = now();

        // "Água / esgoto" → "Água"
        DB::table('despesas_fixas_categorias')
            ->whereIn('nome', ['Água / esgoto', 'Agua / esgoto', 'Água/esgoto', 'Agua/esgoto'])
            ->update(['nome' => 'Água', 'updated_at' => $now]);

        // Evita duplicata se já existir "Água" e ainda houver variação antiga
        $agua = DB::table('despesas_fixas_categorias')->where('nome', 'Água')->orderBy('id')->first();
        if ($agua) {
            DB::table('despesas_fixas_categorias')
                ->where('nome', 'like', '%esgoto%')
                ->where('id', '!=', $agua->id)
                ->update(['ativo' => false, 'updated_at' => $now]);
        }

        // Nova categoria: Gás
        $gas = DB::table('despesas_fixas_categorias')->where('nome', 'Gás')->first();
        if (! $gas) {
            $gasAlt = DB::table('despesas_fixas_categorias')->where('nome', 'Gas')->first();
            if ($gasAlt) {
                DB::table('despesas_fixas_categorias')->where('id', $gasAlt->id)->update([
                    'nome' => 'Gás',
                    'ativo' => true,
                    'updated_at' => $now,
                ]);
            } else {
                $ordem = (int) (DB::table('despesas_fixas_categorias')->max('ordem') ?? 0) + 10;
                DB::table('despesas_fixas_categorias')->insert([
                    'nome' => 'Gás',
                    'ordem' => max(35, $ordem),
                    'ativo' => true,
                    'criado_por' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } else {
            DB::table('despesas_fixas_categorias')->where('id', $gas->id)->update([
                'ativo' => true,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sem reversão automática.
    }
};
