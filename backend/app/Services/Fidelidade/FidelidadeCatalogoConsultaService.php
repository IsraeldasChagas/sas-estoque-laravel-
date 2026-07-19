<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class FidelidadeCatalogoConsultaService
{
    public function colunasDisponiveis(): bool
    {
        return Schema::hasTable('fid_programas')
            && Schema::hasColumn('fid_programas', 'catalogo_qtd_escolhas')
            && Schema::hasColumn('fid_programas', 'catalogo_produtos_json');
    }

    public function resgateColunasDisponiveis(): bool
    {
        return Schema::hasTable('fid_resgates')
            && Schema::hasColumn('fid_resgates', 'catalogo_escolhas_json');
    }

    /**
     * Valida e normaliza a escolha do cliente no resgate.
     *
     * @param  array<int, array{produto_id?:int,id?:int,qtd?:int}>|list<int>  $escolhas
     * @return array{json:string,linhas:list<array{id:int,nome:string,qtd:int}>,titulo:string}
     */
    public function normalizarEscolhasResgate(object $programa, int $unidadeFidelidadeId, array $escolhas): array
    {
        $limite = max(1, (int) ($programa->catalogo_qtd_escolhas ?? 1));
        $permitidos = collect($this->produtosDoPrograma($programa, $unidadeFidelidadeId))->keyBy('id');
        if ($permitidos->isEmpty()) {
            throw ValidationException::withMessages([
                'catalogo_escolhas' => ['Nenhum produto configurado na recompensa do programa.'],
            ]);
        }

        $map = [];
        foreach ($escolhas as $item) {
            if (is_numeric($item)) {
                $id = (int) $item;
                $qtd = 1;
            } elseif (is_array($item)) {
                $id = (int) ($item['produto_id'] ?? $item['id'] ?? 0);
                $qtd = max(1, (int) ($item['qtd'] ?? 1));
            } else {
                continue;
            }
            if ($id <= 0 || ! $permitidos->has($id)) {
                throw ValidationException::withMessages([
                    'catalogo_escolhas' => ['Produto escolhido não faz parte das opções da recompensa.'],
                ]);
            }
            $map[$id] = ($map[$id] ?? 0) + $qtd;
        }

        $total = array_sum($map);
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'catalogo_escolhas' => ['Escolha pelo menos 1 produto para resgatar.'],
            ]);
        }
        if ($total > $limite) {
            throw ValidationException::withMessages([
                'catalogo_escolhas' => ['Você pode escolher no máximo '.$limite.' item(ns) no resgate.'],
            ]);
        }
        if ($total < $limite) {
            throw ValidationException::withMessages([
                'catalogo_escolhas' => ['Escolha exatamente '.$limite.' item(ns) para concluir o resgate.'],
            ]);
        }

        $linhas = [];
        $partesTitulo = [];
        foreach ($map as $id => $qtd) {
            $prod = $permitidos->get($id);
            $nome = (string) ($prod['nome'] ?? 'Produto');
            $linhas[] = ['id' => (int) $id, 'nome' => $nome, 'qtd' => (int) $qtd];
            $partesTitulo[] = $qtd > 1 ? ($qtd.'× '.$nome) : $nome;
        }

        return [
            'json' => json_encode($linhas, JSON_UNESCAPED_UNICODE),
            'linhas' => $linhas,
            'titulo' => implode(' + ', $partesTitulo),
        ];
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

        if (Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
            $porFidelidade = DB::table('dlv_loja_config')
                ->where('unidade_fidelidade_id', $unidadeFidelidadeId)
                ->where('ativo', 1)
                ->orderBy('id')
                ->first();
            if ($porFidelidade) {
                return $porFidelidade;
            }
        }

        return DB::table('dlv_loja_config')
            ->where('unidade_id', $unidadeFidelidadeId)
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();
    }

    /**
     * Unidade onde o programa de fidelidade deve ser gravado/consultado.
     * Se a unidade informada for a do Delivery com unidade_fidelidade_id, usa a de fidelidade.
     */
    public function unidadeFidelidadeCanonica(int $unidadeId): int
    {
        if ($unidadeId <= 0 || ! Schema::hasTable('dlv_loja_config')) {
            return $unidadeId;
        }

        if (! Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
            return $unidadeId;
        }

        $loja = DB::table('dlv_loja_config')
            ->where('unidade_id', $unidadeId)
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();

        $fid = (int) ($loja->unidade_fidelidade_id ?? 0);
        if ($loja && $fid > 0 && $fid !== $unidadeId) {
            return $fid;
        }

        return $unidadeId;
    }

    /**
     * Mescla catálogo (consulta) entre programa da unidade fidelidade e da loja Delivery.
     */
    public function mesclarProgramaVitrine(?object $programaFidelidade, ?object $programaDelivery): ?object
    {
        $base = $programaFidelidade ?? $programaDelivery;
        if (! $base) {
            return null;
        }

        $fonteCatalogo = $this->fonteCatalogoConsulta($programaFidelidade, $programaDelivery);
        if (! $fonteCatalogo || $fonteCatalogo === $base) {
            return $base;
        }

        $mesclado = clone $base;
        if ($this->colunasDisponiveis()) {
            $mesclado->catalogo_qtd_escolhas = $fonteCatalogo->catalogo_qtd_escolhas ?? $base->catalogo_qtd_escolhas ?? null;
            $mesclado->catalogo_produtos_json = $fonteCatalogo->catalogo_produtos_json ?? $base->catalogo_produtos_json ?? null;
        }
        $tipoFonte = (string) ($fonteCatalogo->tipo_recompensa_padrao ?? '');
        if (in_array($tipoFonte, ['catalogo_consulta', 'produto'], true)) {
            $mesclado->tipo_recompensa_padrao = 'catalogo_consulta';
        }

        return $mesclado;
    }

    /**
     * @return list<array{id:int,nome:string,preco?:float}>
     */
    public function produtosDoPrograma(object $programa, ?int $unidadeFidelidadeContexto = null): array
    {
        $unidadeId = $unidadeFidelidadeContexto > 0
            ? $unidadeFidelidadeContexto
            : (int) ($programa->unidade_id ?? 0);
        $itens = $this->decodificarProdutosJson($programa->catalogo_produtos_json ?? null);
        if ($itens === [] && $unidadeId > 0) {
            return [];
        }

        return $this->hydrateProdutos($unidadeId, $itens);
    }

    /**
     * @param  list<array{id:int,nome?:string,preco?:float}>  $itens
     * @return list<array{id:int,nome:string,preco?:float}>
     */
    public function hydrateProdutos(int $unidadeFidelidadeId, array $itens): array
    {
        if ($itens === []) {
            return [];
        }

        $map = collect($this->produtosAtivos($unidadeFidelidadeId))->keyBy('id');
        $out = [];
        foreach ($itens as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $live = $map->get($id);
            $nome = trim((string) ($live['nome'] ?? $item['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $row = ['id' => $id, 'nome' => $nome];
            if ($live !== null) {
                $row['preco'] = (float) $live['preco'];
                if (! empty($live['foto_url'])) {
                    $row['foto_url'] = (string) $live['foto_url'];
                }
            } else {
                if (array_key_exists('preco', $item)) {
                    $row['preco'] = (float) $item['preco'];
                }
                if (! empty($item['foto_url'])) {
                    $row['foto_url'] = (string) $item['foto_url'];
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    private function fonteCatalogoConsulta(?object $programaFidelidade, ?object $programaDelivery): ?object
    {
        $candidatos = array_filter([$programaFidelidade, $programaDelivery]);
        $melhor = null;
        $melhorScore = -1;
        foreach ($candidatos as $programa) {
            $score = $this->scoreCatalogoConsulta($programa);
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $programa;
            }
        }

        return $melhorScore > 0 ? $melhor : null;
    }

    private function scoreCatalogoConsulta(?object $programa): int
    {
        if (! $programa) {
            return 0;
        }
        $tipo = (string) ($programa->tipo_recompensa_padrao ?? '');
        if (! in_array($tipo, ['catalogo_consulta', 'produto'], true)) {
            return 0;
        }
        $produtos = $this->decodificarProdutosJson($programa->catalogo_produtos_json ?? null);

        return count($produtos) > 0 ? 10 + count($produtos) : 0;
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

        $cols = ['id', 'nome', 'preco', 'visivel_loja'];
        if (Schema::hasColumn('dlv_produtos', 'foto_path')) {
            $cols[] = 'foto_path';
        }

        return $query->orderBy('nome')
            ->get($cols)
            ->map(function ($row) use ($deliveryUnidadeId) {
                $item = [
                    'id' => (int) $row->id,
                    'nome' => (string) $row->nome,
                    'preco' => (float) $row->preco,
                    'visivel_loja' => (bool) ($row->visivel_loja ?? true),
                ];
                $fotoUrl = $this->fotoUrl($row->foto_path ?? null, $deliveryUnidadeId);
                if ($fotoUrl !== null) {
                    $item['foto_url'] = $fotoUrl;
                }

                return $item;
            })->values()->all();
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
                $payloadItem = [
                    'id' => (int) $item['id'],
                    'nome' => (string) $item['nome'],
                    'preco' => (float) $item['preco'],
                ];
                if (! empty($item['foto_url'])) {
                    $payloadItem['foto_url'] = (string) $item['foto_url'];
                }
                $payload[] = $payloadItem;
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
            if ($id <= 0) {
                continue;
            }
            $nome = trim((string) ($item['nome'] ?? ''));
            $row = ['id' => $id];
            if ($nome !== '') {
                $row['nome'] = $nome;
            }
            if (array_key_exists('preco', $item)) {
                $row['preco'] = (float) $item['preco'];
            }
            if (! empty($item['foto_url'])) {
                $row['foto_url'] = (string) $item['foto_url'];
            }
            $out[] = $row;
        }

        return $out;
    }

    private function fotoUrl(?string $path, int $deliveryUnidadeId): ?string
    {
        $rel = ltrim(str_replace('\\', '/', (string) $path), '/');
        $prefix = "uploads/delivery/produtos/{$deliveryUnidadeId}/";

        return $rel !== '' && ! str_contains($rel, '..') && str_starts_with($rel, $prefix) ? '/'.$rel : null;
    }
}
