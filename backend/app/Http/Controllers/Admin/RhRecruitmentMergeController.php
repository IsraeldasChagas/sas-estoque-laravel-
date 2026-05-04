<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reinsere no banco apenas linhas de recrutamento cujo id ainda não existe
 * (recuperação a partir de um backup JSON, sem apagar candidatos atuais).
 */
class RhRecruitmentMergeController
{
    /**
     * Ordem respeitando FKs: vagas → candidatos → filhos.
     *
     * @return list<string>
     */
    public static function recruitmentTablesInOrder(): array
    {
        return [
            'rh_vagas',
            'rh_candidatos',
            'rh_curriculos',
            'rh_entrevistas',
            'rh_documentos',
            'rh_historico',
        ];
    }

    /**
     * @return array<string, int> inseridos por tabela
     */
    public static function mergeFromSnapshot(array $snapshot): array
    {
        $counts = [];
        if (! isset($snapshot['tabelas']) || ! is_array($snapshot['tabelas'])) {
            return $counts;
        }

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach (self::recruitmentTablesInOrder() as $tabela) {
                    if (! Schema::hasTable($tabela)) {
                        $counts[$tabela] = 0;

                        continue;
                    }
                    $registros = $snapshot['tabelas'][$tabela] ?? null;
                    if (! is_array($registros)) {
                        $counts[$tabela] = 0;

                        continue;
                    }
                    $mapaColunas = array_flip(Schema::getColumnListing($tabela));
                    $n = 0;
                    foreach ($registros as $r) {
                        $linha = array_intersect_key((array) $r, $mapaColunas);
                        if ($linha === [] || ! isset($linha['id'])) {
                            continue;
                        }
                        if (DB::table($tabela)->where('id', $linha['id'])->exists()) {
                            continue;
                        }
                        DB::table($tabela)->insert($linha);
                        $n++;
                    }
                    $counts[$tabela] = $n;
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $counts;
    }
}
