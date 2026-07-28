<?php

namespace App\Support\Delivery;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CardapioProdutoUnidadeSupport
{
    public static function tabelaAtiva(): bool
    {
        return Schema::hasTable('dlv_produto_unidades');
    }

    public static function escopoQueryDisponivelNaUnidade(
        Builder $query,
        int $unidadeId,
        string $produtoIdColumn = 'p.id',
        string $ownerColumn = 'p.unidade_id',
    ): void {
        if ($unidadeId <= 0) {
            return;
        }

        if (! self::tabelaAtiva()) {
            $query->where($ownerColumn, $unidadeId);

            return;
        }

        $query->where(function (Builder $w) use ($unidadeId, $produtoIdColumn, $ownerColumn) {
            $w->whereExists(function (Builder $sub) use ($unidadeId, $produtoIdColumn) {
                $sub->select(DB::raw(1))
                    ->from('dlv_produto_unidades as dpu')
                    ->whereColumn('dpu.produto_id', $produtoIdColumn)
                    ->where('dpu.unidade_id', $unidadeId);
            })->orWhere(function (Builder $w2) use ($unidadeId, $produtoIdColumn, $ownerColumn) {
                $w2->where($ownerColumn, $unidadeId)
                    ->whereNotExists(function (Builder $sub) use ($produtoIdColumn) {
                        $sub->select(DB::raw(1))
                            ->from('dlv_produto_unidades as dpu2')
                            ->whereColumn('dpu2.produto_id', $produtoIdColumn);
                    });
            });
        });
    }

    public static function produtoDisponivelNaUnidade(int $produtoId, int $unidadeId): bool
    {
        if ($produtoId <= 0 || $unidadeId <= 0) {
            return false;
        }

        $row = DB::table('dlv_produtos')->where('id', $produtoId)->first(['id', 'unidade_id']);
        if (! $row) {
            return false;
        }

        if (! self::tabelaAtiva()) {
            return (int) $row->unidade_id === $unidadeId;
        }

        $vinculado = DB::table('dlv_produto_unidades')
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->exists();
        if ($vinculado) {
            return true;
        }

        $temVinculos = DB::table('dlv_produto_unidades')->where('produto_id', $produtoId)->exists();
        if (! $temVinculos) {
            return (int) $row->unidade_id === $unidadeId;
        }

        return false;
    }

    /** @return list<int> */
    public static function unidadesDoProduto(int $produtoId, int $unidadeDonoFallback): array
    {
        if ($produtoId <= 0) {
            return $unidadeDonoFallback > 0 ? [$unidadeDonoFallback] : [];
        }

        if (! self::tabelaAtiva()) {
            return $unidadeDonoFallback > 0 ? [$unidadeDonoFallback] : [];
        }

        $ids = DB::table('dlv_produto_unidades')
            ->where('produto_id', $produtoId)
            ->orderBy('unidade_id')
            ->pluck('unidade_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if ($ids === []) {
            return $unidadeDonoFallback > 0 ? [$unidadeDonoFallback] : [];
        }

        return $ids;
    }

    /** @param  list<int>  $unidadeIds */
    public static function sincronizar(int $produtoId, array $unidadeIds, int $unidadeDono): void
    {
        if ($produtoId <= 0 || ! self::tabelaAtiva()) {
            return;
        }

        $unidadeIds = array_values(array_unique(array_filter(array_map('intval', $unidadeIds), fn (int $v) => $v > 0)));
        if ($unidadeDono > 0 && ! in_array($unidadeDono, $unidadeIds, true)) {
            $unidadeIds[] = $unidadeDono;
        }
        if ($unidadeIds === [] && $unidadeDono > 0) {
            $unidadeIds = [$unidadeDono];
        }

        sort($unidadeIds);

        DB::table('dlv_produto_unidades')->where('produto_id', $produtoId)->delete();
        $now = now();
        foreach ($unidadeIds as $uid) {
            DB::table('dlv_produto_unidades')->insert([
                'produto_id' => $produtoId,
                'unidade_id' => $uid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param  list<int>  $unidadeIds */
    public static function validarUnidadesExistem(array $unidadeIds): void
    {
        if ($unidadeIds === [] || ! Schema::hasTable('unidades')) {
            return;
        }

        $ok = DB::table('unidades')->whereIn('id', $unidadeIds)->count();
        if ($ok !== count($unidadeIds)) {
            throw new \InvalidArgumentException('Uma ou mais unidades selecionadas são inválidas.');
        }
    }
}
