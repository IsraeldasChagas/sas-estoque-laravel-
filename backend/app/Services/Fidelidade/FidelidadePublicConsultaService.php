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
        $fidelidadeVinculada = Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')
            && (int) ($config->unidade_fidelidade_id ?? 0) > 0;

        if ($fidelidadeVinculada) {
            return $this->buscarContaAtivaNaUnidade($unidadeFid, $telNorm);
        }

        foreach (array_values(array_unique([$unidadeFid, $unidadeVitrine])) as $uid) {
            $conta = $this->buscarContaAtivaNaUnidade($uid, $telNorm);
            if ($conta) {
                return $conta;
            }
        }

        return null;
    }

    public function buscarContaPorId(int $contaId, string $telNorm): ?object
    {
        if ($contaId <= 0) {
            return null;
        }

        return DB::table('fid_contas')
            ->where('id', $contaId)
            ->where('telefone_normalizado', $telNorm)
            ->where('status', 'ativo')
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
