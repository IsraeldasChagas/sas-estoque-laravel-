<?php

namespace App\Support;

use App\Support\Delivery\CardapioProdutoUnidadeSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque comercial do cardápio (Estoque B).
 * Independente do estoque administrativo (produtos / stock_lotes).
 */
final class CardapioEstoqueSupport
{
    public const TIPO_ENTRADA = 'ENTRADA';

    public const TIPO_SAIDA = 'SAIDA';

    public const TIPO_AJUSTE = 'AJUSTE';

    public const TIPO_ESTORNO = 'ESTORNO';

    public const ORIGEM_ABASTECIMENTO = 'ABASTECIMENTO';

    public const ORIGEM_PRODUCAO = 'PRODUCAO';

    public const ORIGEM_VENDA_PDV = 'VENDA_PDV';

    public const ORIGEM_VENDA_MESA = 'VENDA_MESA';

    public const ORIGEM_VENDA_DELIVERY = 'VENDA_DELIVERY';

    public const ORIGEM_MANUAL = 'MANUAL';

    public const ORIGEM_CANCELAMENTO = 'CANCELAMENTO';

    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('cardapio_estoque_saldos')
            && Schema::hasTable('cardapio_estoque_movimentacoes');
    }

    public static function controlaEstoque(int $dlvProdutoId): bool
    {
        if (! Schema::hasTable('dlv_produtos') || $dlvProdutoId <= 0) {
            return false;
        }
        $row = DB::table('dlv_produtos')->where('id', $dlvProdutoId)->first();
        if (! $row) {
            return false;
        }
        if (! Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio')) {
            return true;
        }

        return (bool) ($row->controla_estoque_cardapio ?? true);
    }

    public static function saldo(int $unidadeId, int $dlvProdutoId): float
    {
        if (! self::moduloAtivo() || $unidadeId <= 0 || $dlvProdutoId <= 0) {
            return 0.0;
        }
        $qtd = DB::table('cardapio_estoque_saldos')
            ->where('unidade_id', $unidadeId)
            ->where('dlv_produto_id', $dlvProdutoId)
            ->value('quantidade');

        return round((float) ($qtd ?? 0), 4);
    }

    /**
     * @return array{ok: bool, message?: string, saldo?: float}
     */
    public static function validarSaldo(int $unidadeId, int $dlvProdutoId, float $quantidade): array
    {
        if (! self::moduloAtivo()) {
            return ['ok' => true];
        }
        if (! self::controlaEstoque($dlvProdutoId)) {
            return ['ok' => true, 'saldo' => null];
        }
        if ($quantidade <= 0) {
            return ['ok' => false, 'message' => 'Quantidade inválida para baixa do cardápio.'];
        }
        $saldo = self::saldo($unidadeId, $dlvProdutoId);
        if ($saldo + 0.0001 < $quantidade) {
            $nome = (string) (DB::table('dlv_produtos')->where('id', $dlvProdutoId)->value('nome') ?? "Item #{$dlvProdutoId}");

            return [
                'ok' => false,
                'saldo' => $saldo,
                'message' => "Sem estoque no cardápio para \"{$nome}\". Necessário: {$quantidade}, disponível: {$saldo}. Abasteça em Cardápio → Estoque.",
            ];
        }

        return ['ok' => true, 'saldo' => $saldo];
    }

    /**
     * @param  array{venda_id?: int|null, comanda_id?: int|null, dlv_pedido_id?: int|null, producao_id?: int|null, usuario_id?: int|null, motivo?: string|null}  $meta
     * @return array{movimentacao_id: int, saldo_apos: float}
     */
    public static function entrada(
        int $unidadeId,
        int $dlvProdutoId,
        float $quantidade,
        string $origem = self::ORIGEM_ABASTECIMENTO,
        array $meta = []
    ): array {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de entrada deve ser positiva.');
        }

        return self::aplicarMovimento($unidadeId, $dlvProdutoId, self::TIPO_ENTRADA, $origem, $quantidade, $meta);
    }

    /**
     * @param  array{venda_id?: int|null, comanda_id?: int|null, dlv_pedido_id?: int|null, producao_id?: int|null, usuario_id?: int|null, motivo?: string|null}  $meta
     * @return array{movimentacao_id: int, saldo_apos: float}
     */
    public static function saida(
        int $unidadeId,
        int $dlvProdutoId,
        float $quantidade,
        string $origem,
        array $meta = [],
        bool $forcar = false
    ): array {
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade de saída deve ser positiva.');
        }
        if (! $forcar) {
            $val = self::validarSaldo($unidadeId, $dlvProdutoId, $quantidade);
            if (! ($val['ok'] ?? false)) {
                throw new \RuntimeException($val['message'] ?? 'Saldo insuficiente no estoque do cardápio.');
            }
        }

        return self::aplicarMovimento($unidadeId, $dlvProdutoId, self::TIPO_SAIDA, $origem, $quantidade, $meta);
    }

    /**
     * Define o saldo absoluto (ajuste de inventário).
     *
     * @param  array{usuario_id?: int|null, motivo?: string|null}  $meta
     * @return array{movimentacao_id: int, saldo_apos: float}
     */
    public static function ajustar(
        int $unidadeId,
        int $dlvProdutoId,
        float $novoSaldo,
        array $meta = []
    ): array {
        if ($novoSaldo < 0) {
            throw new \InvalidArgumentException('Saldo não pode ser negativo.');
        }
        $atual = self::saldo($unidadeId, $dlvProdutoId);
        $delta = round($novoSaldo - $atual, 4);
        if (abs($delta) < 0.0001) {
            return ['movimentacao_id' => 0, 'saldo_apos' => $atual];
        }
        $tipo = $delta > 0 ? self::TIPO_ENTRADA : self::TIPO_SAIDA;

        return self::aplicarMovimento(
            $unidadeId,
            $dlvProdutoId,
            self::TIPO_AJUSTE,
            self::ORIGEM_MANUAL,
            abs($delta),
            array_merge($meta, ['motivo' => ($meta['motivo'] ?? null) ?: "Ajuste inventário ({$atual} → {$novoSaldo})"]),
            $novoSaldo
        );
    }

    /**
     * @param  array{venda_id?: int|null, comanda_id?: int|null, dlv_pedido_id?: int|null, usuario_id?: int|null, motivo?: string|null}  $meta
     * @return array{movimentacao_id: int, saldo_apos: float}|null
     */
    public static function estornarSaida(
        int $unidadeId,
        int $dlvProdutoId,
        float $quantidade,
        array $meta = []
    ): ?array {
        if (! self::moduloAtivo() || ! self::controlaEstoque($dlvProdutoId) || $quantidade <= 0) {
            return null;
        }

        return self::aplicarMovimento(
            $unidadeId,
            $dlvProdutoId,
            self::TIPO_ESTORNO,
            self::ORIGEM_CANCELAMENTO,
            $quantidade,
            $meta
        );
    }

    /**
     * Baixa na venda comercial. Retorna null se item não controla estoque do cardápio.
     *
     * @param  array{venda_id?: int|null, comanda_id?: int|null, usuario_id?: int|null, origem_venda?: string|null}  $meta
     * @return array{movimentacao_id: int, saldo_apos: float}|null
     */
    public static function baixarVenda(
        int $unidadeId,
        int $dlvProdutoId,
        float $quantidade,
        array $meta = []
    ): ?array {
        if (! self::moduloAtivo() || $dlvProdutoId <= 0 || ! self::controlaEstoque($dlvProdutoId)) {
            return null;
        }
        $origemVenda = (string) ($meta['origem_venda'] ?? 'balcao');
        $origem = match ($origemVenda) {
            'mesa' => self::ORIGEM_VENDA_MESA,
            'delivery' => self::ORIGEM_VENDA_DELIVERY,
            default => self::ORIGEM_VENDA_PDV,
        };

        return self::saida($unidadeId, $dlvProdutoId, $quantidade, $origem, $meta, false);
    }

    /**
     * @return array{deve_baixar_admin: bool, tipo_venda: string}
     */
    public static function politicaBaixaAdmin(?int $dlvProdutoId): array
    {
        $tipo = 'revenda';
        if ($dlvProdutoId && Schema::hasTable('dlv_produtos')) {
            $row = DB::table('dlv_produtos')->where('id', $dlvProdutoId)->first();
            if ($row && Schema::hasColumn('dlv_produtos', 'tipo_venda')) {
                $tipo = (string) ($row->tipo_venda ?? 'revenda');
            }
        }
        // Prato: só Estoque B (insumos já saíram na produção). Revenda: B + A.
        return [
            'deve_baixar_admin' => $tipo !== 'prato',
            'tipo_venda' => $tipo,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarSaldos(int $unidadeId, ?string $search = null): array
    {
        if (! self::moduloAtivo() || $unidadeId <= 0 || ! Schema::hasTable('dlv_produtos')) {
            return [];
        }

        $q = DB::table('dlv_produtos as p')
            ->leftJoin('dlv_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('cardapio_estoque_saldos as s', function ($j) use ($unidadeId) {
                $j->on('s.dlv_produto_id', '=', 'p.id')->where('s.unidade_id', '=', $unidadeId);
            })
            ->where('p.unidade_id', $unidadeId)
            ->where('p.ativo', 1);

        if ($search) {
            $term = '%' . $search . '%';
            $q->where('p.nome', 'like', $term);
        }

        $rows = $q->orderBy('p.nome')
            ->limit(800)
            ->get([
                'p.id',
                'p.nome',
                'p.tipo_venda',
                'p.controla_estoque_cardapio',
                'p.estoque_produto_id',
                'p.ficha_tecnica_id',
                'p.preco',
                'c.nome as categoria_nome',
                's.quantidade',
                's.estoque_minimo',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $controla = Schema::hasColumn('dlv_produtos', 'controla_estoque_cardapio')
                ? (bool) ($r->controla_estoque_cardapio ?? true)
                : true;
            $qtd = round((float) ($r->quantidade ?? 0), 4);
            $min = round((float) ($r->estoque_minimo ?? 0), 4);
            $out[] = [
                'dlv_produto_id' => (int) $r->id,
                'nome' => (string) $r->nome,
                'categoria_nome' => $r->categoria_nome,
                'tipo_venda' => (string) ($r->tipo_venda ?? 'revenda'),
                'controla_estoque_cardapio' => $controla,
                'estoque_produto_id' => $r->estoque_produto_id ? (int) $r->estoque_produto_id : null,
                'ficha_tecnica_id' => $r->ficha_tecnica_id ? (int) $r->ficha_tecnica_id : null,
                'preco' => round((float) ($r->preco ?? 0), 2),
                'quantidade' => $qtd,
                'estoque_minimo' => $min,
                'abaixo_minimo' => $controla && $min > 0 && $qtd + 0.0001 < $min,
                'sem_estoque' => $controla && $qtd <= 0.0001,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarMovimentacoes(int $unidadeId, ?int $dlvProdutoId = null, int $limit = 200): array
    {
        if (! self::moduloAtivo() || $unidadeId <= 0) {
            return [];
        }
        $q = DB::table('cardapio_estoque_movimentacoes as m')
            ->leftJoin('dlv_produtos as p', 'p.id', '=', 'm.dlv_produto_id')
            ->where('m.unidade_id', $unidadeId);
        if ($dlvProdutoId) {
            $q->where('m.dlv_produto_id', $dlvProdutoId);
        }
        $rows = $q->orderByDesc('m.id')
            ->limit(max(1, min(500, $limit)))
            ->get([
                'm.*',
                'p.nome as produto_nome',
            ]);

        return $rows->map(static fn ($r) => [
            'id' => (int) $r->id,
            'dlv_produto_id' => (int) $r->dlv_produto_id,
            'produto_nome' => (string) ($r->produto_nome ?? ''),
            'tipo' => (string) $r->tipo,
            'origem' => (string) $r->origem,
            'quantidade' => round((float) $r->quantidade, 4),
            'saldo_apos' => round((float) $r->saldo_apos, 4),
            'venda_id' => $r->venda_id ? (int) $r->venda_id : null,
            'comanda_id' => $r->comanda_id ? (int) $r->comanda_id : null,
            'dlv_pedido_id' => $r->dlv_pedido_id ? (int) $r->dlv_pedido_id : null,
            'motivo' => $r->motivo,
            'created_at' => (string) $r->created_at,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{movimentacao_id: int, saldo_apos: float}
     */
    private static function aplicarMovimento(
        int $unidadeId,
        int $dlvProdutoId,
        string $tipo,
        string $origem,
        float $quantidade,
        array $meta = [],
        ?float $saldoForcado = null
    ): array {
        if (! self::moduloAtivo()) {
            throw new \RuntimeException('Módulo de estoque do cardápio indisponível. Rode as migrations.');
        }
        if ($unidadeId <= 0 || $dlvProdutoId <= 0) {
            throw new \InvalidArgumentException('Unidade e item do cardápio são obrigatórios.');
        }
        if (! DB::table('dlv_produtos')->where('id', $dlvProdutoId)->exists()) {
            throw new \InvalidArgumentException('Item do cardápio não encontrado.');
        }

        return DB::transaction(function () use ($unidadeId, $dlvProdutoId, $tipo, $origem, $quantidade, $meta, $saldoForcado) {
            $row = DB::table('cardapio_estoque_saldos')
                ->where('unidade_id', $unidadeId)
                ->where('dlv_produto_id', $dlvProdutoId)
                ->lockForUpdate()
                ->first();

            $atual = $row ? round((float) $row->quantidade, 4) : 0.0;

            if ($saldoForcado !== null) {
                $novo = round($saldoForcado, 4);
            } elseif (in_array($tipo, [self::TIPO_ENTRADA, self::TIPO_ESTORNO], true)) {
                $novo = round($atual + $quantidade, 4);
            } else {
                // SAIDA ou AJUSTE sem saldo forçado → reduz
                $novo = round($atual - $quantidade, 4);
            }

            if ($novo < -0.0001) {
                throw new \RuntimeException('Saldo do cardápio ficaria negativo.');
            }
            $novo = max(0, $novo);

            $agora = now();
            if ($row) {
                DB::table('cardapio_estoque_saldos')->where('id', $row->id)->update([
                    'quantidade' => $novo,
                    'updated_at' => $agora,
                ]);
            } else {
                DB::table('cardapio_estoque_saldos')->insert([
                    'unidade_id' => $unidadeId,
                    'dlv_produto_id' => $dlvProdutoId,
                    'quantidade' => $novo,
                    'estoque_minimo' => 0,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            $movId = DB::table('cardapio_estoque_movimentacoes')->insertGetId([
                'unidade_id' => $unidadeId,
                'dlv_produto_id' => $dlvProdutoId,
                'tipo' => $tipo,
                'origem' => $origem,
                'quantidade' => round($quantidade, 4),
                'saldo_apos' => $novo,
                'venda_id' => isset($meta['venda_id']) ? (int) $meta['venda_id'] : null,
                'comanda_id' => isset($meta['comanda_id']) ? (int) $meta['comanda_id'] : null,
                'dlv_pedido_id' => isset($meta['dlv_pedido_id']) ? (int) $meta['dlv_pedido_id'] : null,
                'producao_id' => isset($meta['producao_id']) ? (int) $meta['producao_id'] : null,
                'usuario_id' => isset($meta['usuario_id']) ? (int) $meta['usuario_id'] : null,
                'motivo' => isset($meta['motivo']) ? mb_substr((string) $meta['motivo'], 0, 255) : null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            self::sincronizarContadorLegado($dlvProdutoId, $unidadeId, $novo);

            return ['movimentacao_id' => (int) $movId, 'saldo_apos' => $novo];
        });
    }

    private static function sincronizarContadorLegado(int $dlvProdutoId, int $unidadeId, float $novo): void
    {
        if (! Schema::hasTable('dlv_produtos') || ! Schema::hasColumn('dlv_produtos', 'estoque')) {
            return;
        }
        // Só espelha se o produto "dono" for da mesma unidade (modelo clássico).
        $dono = (int) (DB::table('dlv_produtos')->where('id', $dlvProdutoId)->value('unidade_id') ?? 0);
        if ($dono === $unidadeId) {
            DB::table('dlv_produtos')->where('id', $dlvProdutoId)->update([
                'estoque' => (int) max(0, round($novo)),
                'updated_at' => now(),
            ]);
        }
    }

    public static function definirMinimo(int $unidadeId, int $dlvProdutoId, float $minimo): void
    {
        if (! self::moduloAtivo() || $minimo < 0) {
            return;
        }
        $row = DB::table('cardapio_estoque_saldos')
            ->where('unidade_id', $unidadeId)
            ->where('dlv_produto_id', $dlvProdutoId)
            ->first();
        $agora = now();
        if ($row) {
            DB::table('cardapio_estoque_saldos')->where('id', $row->id)->update([
                'estoque_minimo' => round($minimo, 4),
                'updated_at' => $agora,
            ]);
        } else {
            DB::table('cardapio_estoque_saldos')->insert([
                'unidade_id' => $unidadeId,
                'dlv_produto_id' => $dlvProdutoId,
                'quantidade' => 0,
                'estoque_minimo' => round($minimo, 4),
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    /**
     * Após finalizar produção: entra a quantidade produzida nos itens do cardápio
     * vinculados à ficha e/ou ao produto final, na unidade da produção.
     *
     * @return list<array{dlv_produto_id: int, nome: string, quantidade: float, movimentacao_id: int, saldo_apos: float}>
     */
    public static function entrarDaProducao(
        int $unidadeId,
        float $quantidadeProduzida,
        int $producaoId,
        ?int $fichaTecnicaId,
        ?int $produtoFinalId,
        ?int $usuarioId = null
    ): array {
        if (! self::moduloAtivo() || $unidadeId <= 0 || $quantidadeProduzida <= 0) {
            return [];
        }
        if (! Schema::hasTable('dlv_produtos')) {
            return [];
        }

        $q = DB::table('dlv_produtos as p')->where('p.ativo', 1);
        CardapioProdutoUnidadeSupport::escopoQueryDisponivelNaUnidade($q, $unidadeId, 'p.id', 'p.unidade_id');

        $q->where(function ($w) use ($fichaTecnicaId, $produtoFinalId) {
            $linked = false;
            if ($fichaTecnicaId && Schema::hasColumn('dlv_produtos', 'ficha_tecnica_id')) {
                $w->where('p.ficha_tecnica_id', $fichaTecnicaId);
                $linked = true;
            }
            if ($produtoFinalId) {
                if ($linked) {
                    $w->orWhere('p.estoque_produto_id', $produtoFinalId);
                } else {
                    $w->where('p.estoque_produto_id', $produtoFinalId);
                    $linked = true;
                }
            }
            if (! $linked) {
                $w->whereRaw('1 = 0');
            }
        });

        $itens = $q->select('p.id', 'p.nome', 'p.controla_estoque_cardapio')->get();
        if ($itens->isEmpty()) {
            return [];
        }

        $entradas = [];
        foreach ($itens as $item) {
            $dlvId = (int) $item->id;
            if (! self::controlaEstoque($dlvId)) {
                continue;
            }
            $res = self::entrada(
                $unidadeId,
                $dlvId,
                $quantidadeProduzida,
                self::ORIGEM_PRODUCAO,
                [
                    'producao_id' => $producaoId,
                    'usuario_id' => $usuarioId,
                    'motivo' => 'Produção #' . $producaoId . ' → cardápio',
                ]
            );
            $entradas[] = [
                'dlv_produto_id' => $dlvId,
                'nome' => (string) $item->nome,
                'quantidade' => round($quantidadeProduzida, 4),
                'movimentacao_id' => (int) $res['movimentacao_id'],
                'saldo_apos' => (float) $res['saldo_apos'],
            ];
        }

        return $entradas;
    }
};
