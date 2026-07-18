<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve cartão fidelidade na vitrine pública quando delivery e reservas usam unidades diferentes.
 */
final class FidelidadePublicConsultaService
{
    public function unidadeFidelidade(object $config): int
    {
        if (Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
            $alt = (int) ($config->unidade_fidelidade_id ?? 0);
            if ($alt > 0) {
                return $alt;
            }
        }

        return (int) $config->unidade_id;
    }

    public function buscarContaAtiva(object $config, string $telNorm): ?object
    {
        $unidadeVitrine = (int) $config->unidade_id;
        $unidadeFid = $this->unidadeFidelidade($config);

        foreach (array_values(array_unique([$unidadeFid, $unidadeVitrine])) as $uid) {
            $conta = $this->buscarContaAtivaNaUnidade($uid, $telNorm);
            if ($conta) {
                return $conta;
            }
        }

        if (! Schema::hasTable('fid_programas')) {
            return null;
        }

        return DB::table('fid_contas')
            ->join('fid_programas', 'fid_programas.unidade_id', '=', 'fid_contas.unidade_id')
            ->where('fid_contas.telefone_normalizado', $telNorm)
            ->where('fid_contas.status', 'ativo')
            ->where('fid_programas.ativo', 1)
            ->orderByDesc('fid_contas.updated_at')
            ->select('fid_contas.*')
            ->first();
    }

    public function buscarContaAtivaNaUnidade(int $unidadeId, string $telNorm): ?object
    {
        return DB::table('fid_contas')
            ->where('unidade_id', $unidadeId)
            ->where('telefone_normalizado', $telNorm)
            ->where('status', 'ativo')
            ->first();
    }
}
