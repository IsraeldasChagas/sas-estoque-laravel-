<?php

namespace App\Services\Delivery;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryCatalogoService
{
    public function consulta(int $unidadeId, Request $request): array
    {
        $categoriasQuery = DB::table('dlv_categorias')
            ->where('unidade_id', $unidadeId)
            ->where('ativo', 1);

        if ($categoriaId = (int) $request->query('categoria_id', 0)) {
            $categoriasQuery->where('id', $categoriaId);
        }

        $categorias = $categoriasQuery->orderBy('ordem')->orderBy('nome')->get();

        $produtosQuery = DB::table('dlv_produtos')
            ->where('unidade_id', $unidadeId)
            ->where('ativo', 1)
            ->where('visivel_loja', 1);

        if ($categoriaId) {
            $produtosQuery->where('categoria_id', $categoriaId);
        }

        if ($busca = trim((string) $request->query('busca', $request->query('q', '')))) {
            $like = '%'.$busca.'%';
            $produtosQuery->where(function ($q) use ($like) {
                $q->where('nome', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('descricao', 'like', $like);
            });
        }

        if ($request->filled('estoque_produto_id')) {
            $produtosQuery->where('estoque_produto_id', (int) $request->query('estoque_produto_id'));
        }

        $produtos = $produtosQuery->orderBy('ordem')->orderBy('nome')->get();

        $porCategoria = [];
        foreach ($categorias as $cat) {
            $porCategoria[(int) $cat->id] = [
                'id' => (int) $cat->id,
                'nome' => (string) $cat->nome,
                'ordem' => (int) $cat->ordem,
                'produtos' => [],
            ];
        }

        $semCategoria = [
            'id' => null,
            'nome' => 'Sem categoria',
            'ordem' => 999999,
            'produtos' => [],
        ];

        foreach ($produtos as $produto) {
            $row = [
                'id' => (int) $produto->id,
                'categoria_id' => $produto->categoria_id !== null ? (int) $produto->categoria_id : null,
                'estoque_produto_id' => $produto->estoque_produto_id !== null ? (int) $produto->estoque_produto_id : null,
                'sku' => $produto->sku,
                'nome' => (string) $produto->nome,
                'preco' => (float) $produto->preco,
                'descricao' => $produto->descricao,
                'foto_path' => $produto->foto_path,
                'permite_adicionais' => (bool) $produto->permite_adicionais,
                'apresentacao' => $produto->apresentacao,
            ];
            $cid = $produto->categoria_id !== null ? (int) $produto->categoria_id : null;
            if ($cid !== null && isset($porCategoria[$cid])) {
                $porCategoria[$cid]['produtos'][] = $row;
            } else {
                $semCategoria['produtos'][] = $row;
            }
        }

        $grupos = array_values($porCategoria);
        if ($semCategoria['produtos'] !== []) {
            $grupos[] = $semCategoria;
        }

        return [
            'unidade_id' => $unidadeId,
            'total_produtos' => $produtos->count(),
            'categorias' => $grupos,
            'produtos' => $produtos->map(fn ($p) => [
                'id' => (int) $p->id,
                'categoria_id' => $p->categoria_id !== null ? (int) $p->categoria_id : null,
                'estoque_produto_id' => $p->estoque_produto_id !== null ? (int) $p->estoque_produto_id : null,
                'sku' => $p->sku,
                'nome' => (string) $p->nome,
                'preco' => (float) $p->preco,
                'descricao' => $p->descricao,
                'foto_path' => $p->foto_path,
                'permite_adicionais' => (bool) $p->permite_adicionais,
                'apresentacao' => $p->apresentacao,
            ])->values(),
        ];
    }
}
