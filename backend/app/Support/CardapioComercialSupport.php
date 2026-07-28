<?php

namespace App\Support;

use App\Support\Delivery\CardapioProdutoUnidadeSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CardapioComercialSupport
{
    public static function tabelaDisponivel(): bool
    {
        return Schema::hasTable('dlv_produtos');
    }

    public static function usaCardapioNaUnidade(int $unidadeId): bool
    {
        if (! self::tabelaDisponivel() || $unidadeId <= 0) {
            return false;
        }

        return DB::table('dlv_produtos as p')
            ->where(function ($q) use ($unidadeId) {
                CardapioProdutoUnidadeSupport::escopoQueryDisponivelNaUnidade($q, $unidadeId, 'p.id', 'p.unidade_id');
            })
            ->exists();
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarParaPdv(int $unidadeId, ?string $search = null): array
    {
        if (! self::tabelaDisponivel() || $unidadeId <= 0) {
            return [];
        }

        $q = DB::table('dlv_produtos as p')
            ->leftJoin('dlv_categorias as c', 'c.id', '=', 'p.categoria_id');

        CardapioProdutoUnidadeSupport::escopoQueryDisponivelNaUnidade($q, $unidadeId, 'p.id', 'p.unidade_id');

        $q->where('p.ativo', 1);

        if (Schema::hasColumn('dlv_produtos', 'visivel_pdv')) {
            $q->where('p.visivel_pdv', 1);
        }

        if ($search) {
            $term = '%' . $search . '%';
            $q->where(function ($w) use ($term) {
                $w->where('p.nome', 'like', $term);
                if (Schema::hasColumn('dlv_produtos', 'sku')) {
                    $w->orWhere('p.sku', 'like', $term);
                }
            });
        }

        $rows = $q->select('p.*', 'c.nome as categoria_nome')
            ->orderBy('p.ordem')->orderBy('p.nome')->limit(500)->get();
        $out = [];

        foreach ($rows as $p) {
            $estoqueId = $p->estoque_produto_id !== null ? (int) $p->estoque_produto_id : 0;
            $saldo = $estoqueId > 0 ? ProducaoEstoqueSupport::saldoDisponivel($estoqueId, $unidadeId) : null;
            $catNome = $p->categoria_nome ?? null;
            $categoria = $catNome ? Str::slug(Str::lower($catNome), '_') : 'geral';
            if ($categoria === '') {
                $categoria = 'geral';
            }
            $preco = round((float) ($p->preco ?? 0), 2);
            $estoqueOk = $estoqueId > 0 && $saldo !== null && $saldo > 0.0001;

            $out[] = [
                'id' => (int) $p->id,
                'cardapio_produto_id' => (int) $p->id,
                'estoque_produto_id' => $estoqueId > 0 ? $estoqueId : null,
                'nome' => (string) $p->nome,
                'categoria' => $categoria,
                'categoria_nome' => $catNome,
                'preco' => $preco,
                'saldo' => $saldo,
                'disponivel' => true,
                'estoque_ok' => $estoqueOk,
                'aviso' => $estoqueId <= 0
                    ? 'Vincule o ID produto estoque no cardápio.'
                    : ($estoqueOk ? null : 'Sem saldo nesta unidade — confira o estoque.'),
                'fonte' => 'cardapio',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array{produto_id: int, cardapio_produto_id: ?int, preco_unitario: float, nome: string}
     */
    public static function resolverLinhaVenda(int $unidadeId, array $linha): array
    {
        $cardapioId = (int) ($linha['cardapio_produto_id'] ?? 0);
        $produtoId = (int) ($linha['produto_id'] ?? 0);
        $precoInformado = array_key_exists('preco_unitario', $linha) ? (float) $linha['preco_unitario'] : null;

        if ($cardapioId > 0) {
            return self::resolverItemCardapio($unidadeId, $cardapioId, $precoInformado);
        }

        if ($produtoId > 0 && self::usaCardapioNaUnidade($unidadeId)) {
            $dlv = DB::table('dlv_produtos')
                ->where('unidade_id', $unidadeId)
                ->where('id', $produtoId)
                ->where('ativo', 1)
                ->first();
            if ($dlv) {
                return self::resolverItemCardapio($unidadeId, (int) $dlv->id, $precoInformado);
            }
        }

        if ($produtoId <= 0) {
            throw new \InvalidArgumentException('Informe o item do cardápio ou produto de estoque.');
        }

        $preco = $precoInformado ?? PdvComercialSupport::precoSugeridoProduto($produtoId);
        $nome = (string) (DB::table('produtos')->where('id', $produtoId)->value('nome') ?? 'Produto');

        return [
            'produto_id' => $produtoId,
            'cardapio_produto_id' => null,
            'preco_unitario' => round((float) $preco, 4),
            'nome' => $nome,
        ];
    }

    /**
     * @return array{produto_id: int, cardapio_produto_id: int, preco_unitario: float, nome: string}
     */
    private static function resolverItemCardapio(int $unidadeId, int $cardapioId, ?float $precoInformado): array
    {
        $dlv = DB::table('dlv_produtos')
            ->where('id', $cardapioId)
            ->first();

        if (! $dlv || ! (bool) $dlv->ativo) {
            throw new \InvalidArgumentException('Item do cardápio indisponível ou inativo.');
        }

        if (Schema::hasColumn('dlv_produtos', 'visivel_pdv') && ! (bool) $dlv->visivel_pdv) {
            throw new \InvalidArgumentException('Item não habilitado para PDV/mesas.');
        }

        if (! CardapioProdutoUnidadeSupport::produtoDisponivelNaUnidade($cardapioId, $unidadeId)) {
            throw new \InvalidArgumentException('Item do cardápio não disponível nesta unidade.');
        }

        $estoqueId = $dlv->estoque_produto_id !== null ? (int) $dlv->estoque_produto_id : 0;
        if ($estoqueId <= 0) {
            throw new \InvalidArgumentException('Vincule o item do cardápio a um produto de estoque antes de vender no PDV.');
        }

        $preco = $precoInformado;
        if ($preco === null || $preco <= 0) {
            $preco = (float) ($dlv->preco ?? 0);
        }
        if ($preco <= 0) {
            throw new \InvalidArgumentException('Informe preço unitário — item do cardápio sem preço.');
        }

        return [
            'produto_id' => $estoqueId,
            'cardapio_produto_id' => $cardapioId,
            'preco_unitario' => round($preco, 4),
            'nome' => (string) $dlv->nome,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $itens */
    public static function normalizarItensVenda(int $unidadeId, array $itens): array
    {
        $out = [];
        foreach ($itens as $linha) {
            if (! is_array($linha)) {
                continue;
            }
            $res = self::resolverLinhaVenda($unidadeId, $linha);
            $out[] = [
                'produto_id' => $res['produto_id'],
                'quantidade' => (float) ($linha['quantidade'] ?? 0),
                'preco_unitario' => $res['preco_unitario'],
                'desconto' => (float) ($linha['desconto'] ?? 0),
                'cardapio_produto_id' => $res['cardapio_produto_id'],
            ];
        }

        return $out;
    }
}
