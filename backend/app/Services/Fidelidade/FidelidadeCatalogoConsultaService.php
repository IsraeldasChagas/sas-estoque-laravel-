<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FidelidadeCatalogoConsultaService
{
    public function colunasDisponiveis(): bool
    {
        return Schema::hasTable('fid_programas')
            && Schema::hasColumn('fid_programas', 'catalogo_qtd_escolhas')
            && Schema::hasColumn('fid_programas', 'catalogo_produtos_json');
    }

    public function unidadeDeliveryParaFidelidade(int $unidadeFidelidadeId): int
    {
        $loja = $this->lojaVinculada($unidadeFidelidadeId);

        return $loja ? (int) $loja->unidade_id : $unidadeFidelidadeId;
    }

    public function lojaVinculada(int $unidadeFidelidadeId): ?object
    {
        if (! Schema::hasTable('dlv_loja_config')) {
            return null;
        }

        $loja = DB::table('dlv_loja_config')
            ->where('unidade_id', $unidadeFidelidadeId)
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();

        if ($loja) {
            return $loja;
        }

        if (! Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
            return null;
        }

        return DB::table('dlv_loja_config')
            ->where('unidade_fidelidade_id', $unidadeFidelidadeId)
            ->where('ativo', 1)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<array{id:int,nome:string,preco:float,visivel_loja:bool,categoria_id:?int}>
     */
    public function produtosAtivos(int $unidadeFidelidadeId): array
    {
        if (! Schema::hasTable('dlv_produtos')) {
            return [];
        }

        $deliveryUnidadeId = $this->unidadeDeliveryParaFidelidade($unidadeFidelidadeId);

        $query = DB::table('dlv_produtos')
            ->where('unidade_id', $deliveryUnidadeId)
            ->where('ativo', 1);

        if (Schema::hasColumn('dlv_produtos', 'ordem')) {
            $query->orderBy('ordem');
        }

        return $query->orderBy('nome')
            ->get(['id', 'nome', 'preco', 'visivel_loja'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nome' => (string) $row->nome,
                'preco' => (float) $row->preco,
                'visivel_loja' => (bool) ($row->visivel_loja ?? true),
            ])->values()->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<array{id:int,nome:string,preco:float}>
     */
    public function normalizarProdutosJson(int $unidadeFidelidadeId, array $ids): ?string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($ids === []) {
            return null;
        }

        $map = collect($this->produtosAtivos($unidadeFidelidadeId))->keyBy('id');
        $payload = [];
        foreach ($ids as $id) {
            $item = $map->get($id);
            if ($item) {
                $payload[] = [
                    'id' => (int) $item['id'],
                    'nome' => (string) $item['nome'],
                    'preco' => (float) $item['preco'],
                ];
            }
        }

        if ($payload === []) {
            return null;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /** @return list<array{id:int,nome:string,preco?:float}> */
    public function decodificarProdutosJson(mixed $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        if (is_string($json)) {
            $json = json_decode($json, true);
        }
        if (! is_array($json)) {
            return [];
        }

        $out = [];
        foreach ($json as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            $nome = trim((string) ($item['nome'] ?? ''));
            if ($id <= 0 || $nome === '') {
                continue;
            }
            $row = ['id' => $id, 'nome' => $nome];
            if (array_key_exists('preco', $item)) {
                $row['preco'] = (float) $item['preco'];
            }
            $out[] = $row;
        }

        return $out;
    }
}
