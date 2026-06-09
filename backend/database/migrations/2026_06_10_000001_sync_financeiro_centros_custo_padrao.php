<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CENTROS_PADRAO = ['Administrativo', 'Manutenção', 'Estoque', 'Outros'];

    public function up(): void
    {
        if (! Schema::hasTable('financeiro_centros_custo')) {
            return;
        }

        $now = now();

        DB::table('financeiro_centros_custo')
            ->whereNotIn('nome', self::CENTROS_PADRAO)
            ->update(['ativo' => false, 'updated_at' => $now]);

        foreach (self::CENTROS_PADRAO as $nome) {
            $existe = DB::table('financeiro_centros_custo')->where('nome', $nome)->first();
            if ($existe) {
                DB::table('financeiro_centros_custo')->where('id', $existe->id)->update([
                    'ativo' => true,
                    'updated_at' => $now,
                ]);
                continue;
            }
            DB::table('financeiro_centros_custo')->insert([
                'nome' => $nome,
                'codigo' => strtoupper(substr(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome), 0, 6)),
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sem reversão automática — evita reativar centros antigos em produção.
    }
};
