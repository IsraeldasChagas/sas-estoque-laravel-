<?php

namespace App\Support;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use App\Services\Fiscal\FiscalEmissaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PdvComercialSupport
{
    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('pdv_comandas') && Schema::hasTable('pdv_comanda_itens');
    }

    public static function precoSugeridoProduto(int $produtoId): float
    {
        $p = DB::table('produtos')->where('id', $produtoId)->first();
        if (! $p) {
            return 0.0;
        }
        foreach (['preco_venda', 'preco', 'valor_venda'] as $col) {
            if (Schema::hasColumn('produtos', $col) && isset($p->{$col}) && (float) $p->{$col} > 0) {
                return (float) $p->{$col};
            }
        }
        if (Schema::hasTable('fichas_tecnicas') && Schema::hasColumn('fichas_tecnicas', 'produto_final_id')) {
            $fq = DB::table('fichas_tecnicas')->where('produto_final_id', $produtoId);
            if (Schema::hasColumn('fichas_tecnicas', 'ativo')) {
                $fq->where('ativo', 1);
            }
            $f = $fq->orderByDesc('id')->first(['preco_prato', 'sugestao_venda']);
            if ($f) {
                if (! empty($f->preco_prato) && (float) $f->preco_prato > 0) {
                    return (float) $f->preco_prato;
                }
                if (! empty($f->sugestao_venda) && (float) $f->sugestao_venda > 0) {
                    return (float) $f->sugestao_venda;
                }
            }
        }

        return 0.0;
    }

    /** @return array<int, array<string, mixed>> */
    public static function listarProdutosPdv(int $unidadeId, ?string $search = null): array
    {
        if (CardapioComercialSupport::usaCardapioNaUnidade($unidadeId)) {
            return CardapioComercialSupport::listarParaPdv($unidadeId, $search);
        }

        $q = DB::table('produtos');
        if (Schema::hasColumn('produtos', 'ativo')) {
            $q->where(function ($w) {
                $w->where('produtos.ativo', 1)->orWhere('produtos.ativo', true);
            });
        }
        if ($search) {
            $term = '%' . $search . '%';
            $q->where(function ($w) use ($term) {
                $w->where('produtos.nome', 'like', $term);
                if (Schema::hasColumn('produtos', 'codigo_barras')) {
                    $w->orWhere('produtos.codigo_barras', 'like', $term);
                }
            });
        }
        if ($unidadeId > 0 && Schema::hasTable('stock_lotes')) {
            $q->whereExists(function ($sub) use ($unidadeId) {
                $sub->select(DB::raw(1))
                    ->from('stock_lotes')
                    ->whereColumn('stock_lotes.produto_id', 'produtos.id')
                    ->where('stock_lotes.unidade_id', $unidadeId)
                    ->where('stock_lotes.quantidade', '>', 0);
            });
        }
        $rows = $q->orderBy('produtos.nome')->limit(500)->get();
        if ($rows->isEmpty()) {
            $q2 = DB::table('produtos');
            if (Schema::hasColumn('produtos', 'ativo')) {
                $q2->where(function ($w) {
                    $w->where('produtos.ativo', 1)->orWhere('produtos.ativo', true);
                });
            }
            if ($search) {
                $term = '%' . $search . '%';
                $q2->where(function ($w) use ($term) {
                    $w->where('produtos.nome', 'like', $term);
                });
            }
            if ($unidadeId > 0 && Schema::hasColumn('produtos', 'unidade_id')) {
                $q2->where(function ($w) use ($unidadeId) {
                    $w->where('produtos.unidade_id', $unidadeId)->orWhereNull('produtos.unidade_id');
                });
            }
            $rows = $q2->orderBy('produtos.nome')->limit(500)->get();
        }
        $out = [];
        foreach ($rows as $p) {
            $saldo = $unidadeId > 0 ? ProducaoEstoqueSupport::saldoDisponivel((int) $p->id, $unidadeId) : 0;
            $preco = self::precoSugeridoProduto((int) $p->id);
            $out[] = [
                'id' => (int) $p->id,
                'nome' => $p->nome,
                'categoria' => $p->categoria ?? 'geral',
                'preco' => round($preco, 2),
                'saldo' => $saldo,
                'disponivel' => $saldo > 0.0001,
                'fonte' => 'estoque',
                'cardapio_produto_id' => null,
                'estoque_produto_id' => (int) $p->id,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function mapaSalao(int $unidadeId, ?string $data = null): array
    {
        $data = $data ?? now()->format('Y-m-d');
        $mesas = Mesa::where('unidade_id', $unidadeId)->where('ativo', true)->orderBy('numero_mesa')->get();
        $comandasAbertas = self::moduloAtivo()
            ? DB::table('pdv_comandas')
                ->where('unidade_id', $unidadeId)
                ->whereIn('status', ['aberta', 'aguardando_pagamento'])
                ->get()
                ->keyBy('mesa_id')
            : collect();

        $reservas = ReservaMesa::with('mesa:id,numero_mesa,nome_mesa')
            ->where('unidade_id', $unidadeId)
            ->whereDate('data_reserva', $data)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->get();
        $reservaPorMesa = [];
        foreach ($reservas as $r) {
            $reservaPorMesa[(int) $r->mesa_id] = $r;
        }

        $cards = [];
        foreach ($mesas as $m) {
            $com = $comandasAbertas->get((int) $m->id);
            $res = $reservaPorMesa[(int) $m->id] ?? null;
            $status = 'livre';
            if ($m->status === Mesa::STATUS_BLOQUEADA) {
                $status = 'bloqueada';
            } elseif ($com) {
                $status = $com->status === 'aguardando_pagamento' ? 'aguardando_pagamento' : 'ocupada';
            } elseif ($res) {
                $status = $res->status === ReservaMesa::STATUS_CLIENTE_CHEGOU ? 'ocupada' : 'reservada';
            } elseif ($m->status === Mesa::STATUS_OCUPADA) {
                $status = 'ocupada';
            }

            $cards[] = [
                'mesa_id' => (int) $m->id,
                'numero' => $m->numero_mesa ?? $m->nome_mesa,
                'nome' => $m->nome_mesa,
                'capacidade' => $m->capacidadeMaximaCalculada(),
                'status_operacional' => $status,
                'comanda_id' => $com ? (int) $com->id : null,
                'total_parcial' => $com ? (float) $com->valor_total : 0,
                'reserva_id' => $res ? (int) $res->id : null,
                'reserva_cliente' => $res?->nome_cliente,
                'reserva_hora' => $res ? (string) $res->hora_reserva : null,
            ];
        }

        return ['data' => $data, 'unidade_id' => $unidadeId, 'mesas' => $cards];
    }

    /** @param array<string, mixed> $data */
    public static function abrirComanda(array $data, ?int $usuarioId): array
    {
        if (! self::moduloAtivo()) {
            throw new \RuntimeException('Módulo PDV não migrado.');
        }
        $unidadeId = (int) $data['unidade_id'];
        $mesaId = isset($data['mesa_id']) ? (int) $data['mesa_id'] : null;
        if ($mesaId) {
            $mesa = Mesa::find($mesaId);
            if ($mesa && $mesa->status === Mesa::STATUS_BLOQUEADA) {
                throw new \RuntimeException('Mesa bloqueada — libere no cadastro de mesas.');
            }
            $existente = DB::table('pdv_comandas')
                ->where('mesa_id', $mesaId)
                ->whereIn('status', ['aberta', 'aguardando_pagamento'])
                ->first();
            if ($existente) {
                return self::comandaCompleta((int) $existente->id);
            }
        }
        $id = DB::table('pdv_comandas')->insertGetId([
            'unidade_id' => $unidadeId,
            'mesa_id' => $mesaId,
            'reserva_mesa_id' => isset($data['reserva_mesa_id']) ? (int) $data['reserva_mesa_id'] : null,
            'usuario_id' => $usuarioId,
            'garcom_usuario_id' => isset($data['garcom_usuario_id']) ? (int) $data['garcom_usuario_id'] : null,
            'origem' => $data['origem'] ?? ($mesaId ? 'mesa' : 'balcao'),
            'status' => 'aberta',
            'pessoas' => max(1, (int) ($data['pessoas'] ?? 1)),
            'observacao' => $data['observacao'] ?? null,
            'aberta_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($mesaId && Schema::hasTable('mesas')) {
            DB::table('mesas')->where('id', $mesaId)->update(['status' => Mesa::STATUS_OCUPADA, 'updated_at' => now()]);
        }
        $reservaId = (int) ($data['reserva_mesa_id'] ?? 0);
        if (! $reservaId && $mesaId) {
            $res = ReservaMesa::where('mesa_id', $mesaId)
                ->where('unidade_id', $unidadeId)
                ->whereDate('data_reserva', now()->format('Y-m-d'))
                ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
                ->first();
            if ($res) {
                DB::table('pdv_comandas')->where('id', $id)->update(['reserva_mesa_id' => $res->id]);
                if ($res->status !== ReservaMesa::STATUS_CLIENTE_CHEGOU) {
                    $res->status = ReservaMesa::STATUS_CLIENTE_CHEGOU;
                    $res->save();
                }
            }
        }

        return self::comandaCompleta($id);
    }

    /** @param array<string, mixed> $item */
    public static function adicionarItem(int $comandaId, array $item): array
    {
        $com = DB::table('pdv_comandas')->where('id', $comandaId)->first();
        if (! $com || ! in_array($com->status, ['aberta', 'aguardando_pagamento'], true)) {
            throw new \RuntimeException('Comanda não está aberta.');
        }
        $qtd = (float) $item['quantidade'];
        if ($qtd <= 0) {
            throw new \InvalidArgumentException('Quantidade obrigatória.');
        }
        $unidadeId = (int) $com->unidade_id;
        $resolvido = CardapioComercialSupport::resolverLinhaVenda($unidadeId, $item);
        $produtoId = (int) $resolvido['produto_id'];
        $preco = (float) $resolvido['preco_unitario'];
        $cardapioProdutoId = $resolvido['cardapio_produto_id'] ?? null;
        if ($cardapioProdutoId && CardapioEstoqueSupport::moduloAtivo()) {
            $val = CardapioEstoqueSupport::validarSaldo($unidadeId, (int) $cardapioProdutoId, $qtd);
            if (! ($val['ok'] ?? false)) {
                throw new \RuntimeException($val['message'] ?? 'Sem estoque no cardápio.');
            }
        }
        // CNPJ/estoque admin validados na finalização da venda fiscal.
        $desc = (float) ($item['desconto'] ?? 0);
        $valor = round($preco * $qtd - $desc, 2);
        $insert = [
            'comanda_id' => $comandaId,
            'produto_id' => $produtoId > 0 ? $produtoId : null,
            'quantidade' => $qtd,
            'preco_unitario' => $preco,
            'desconto' => $desc,
            'valor_total' => $valor,
            'observacao' => $item['observacao'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($cardapioProdutoId && Schema::hasColumn('pdv_comanda_itens', 'cardapio_produto_id')) {
            $insert['cardapio_produto_id'] = $cardapioProdutoId;
        }
        DB::table('pdv_comanda_itens')->insert($insert);
        self::recalcularComanda($comandaId);

        return self::comandaCompleta($comandaId);
    }

    public static function removerItem(int $comandaId, int $itemId): array
    {
        DB::table('pdv_comanda_itens')
            ->where('comanda_id', $comandaId)
            ->where('id', $itemId)
            ->update(['status' => 'cancelado', 'updated_at' => now()]);
        self::recalcularComanda($comandaId);

        return self::comandaCompleta($comandaId);
    }

    public static function recalcularComanda(int $comandaId): void
    {
        $itens = DB::table('pdv_comanda_itens')
            ->where('comanda_id', $comandaId)
            ->where('status', 'ativo')
            ->get();
        $sub = $itens->sum(fn ($i) => (float) $i->valor_total);
        $com = DB::table('pdv_comandas')->where('id', $comandaId)->first();
        $desc = (float) ($com->desconto ?? 0);
        $acr = (float) ($com->acrescimo ?? 0);
        $total = max(0, round($sub - $desc + $acr, 2));
        DB::table('pdv_comandas')->where('id', $comandaId)->update([
            'valor_subtotal' => round($sub, 2),
            'valor_total' => $total,
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function comandaCompleta(int $comandaId): array
    {
        $com = DB::table('pdv_comandas')->where('id', $comandaId)->first();
        if (! $com) {
            throw new \RuntimeException('Comanda não encontrada.');
        }
        $itensQuery = DB::table('pdv_comanda_itens')
            ->leftJoin('produtos', 'pdv_comanda_itens.produto_id', '=', 'produtos.id')
            ->where('pdv_comanda_itens.comanda_id', $comandaId)
            ->where('pdv_comanda_itens.status', 'ativo');

        if (Schema::hasTable('dlv_produtos') && Schema::hasColumn('pdv_comanda_itens', 'cardapio_produto_id')) {
            $itensQuery->leftJoin('dlv_produtos as dlv', 'pdv_comanda_itens.cardapio_produto_id', '=', 'dlv.id');
            $itensQuery->select(
                'pdv_comanda_itens.*',
                DB::raw('COALESCE(dlv.nome, produtos.nome) as produto_nome')
            );
        } else {
            $itensQuery->select('pdv_comanda_itens.*', 'produtos.nome as produto_nome');
        }

        $itens = $itensQuery->orderBy('pdv_comanda_itens.id')->get();

        return [
            'comanda' => $com,
            'itens' => $itens,
        ];
    }

    public static function atualizarComanda(int $comandaId, array $data): array
    {
        $com = DB::table('pdv_comandas')->where('id', $comandaId)->first();
        if (! $com || ! in_array($com->status, ['aberta', 'aguardando_pagamento'], true)) {
            throw new \RuntimeException('Comanda não editável.');
        }
        $upd = [];
        if (isset($data['pessoas'])) {
            $upd['pessoas'] = max(1, (int) $data['pessoas']);
        }
        if (isset($data['desconto'])) {
            $upd['desconto'] = max(0, (float) $data['desconto']);
        }
        if (isset($data['acrescimo'])) {
            $upd['acrescimo'] = max(0, (float) $data['acrescimo']);
        }
        if (isset($data['status']) && $data['status'] === 'aguardando_pagamento') {
            $upd['status'] = 'aguardando_pagamento';
        }
        if ($upd) {
            $upd['updated_at'] = now();
            DB::table('pdv_comandas')->where('id', $comandaId)->update($upd);
            self::recalcularComanda($comandaId);
        }

        return self::comandaCompleta($comandaId);
    }

    /** @return array<int, object> */
    public static function comandasAbertas(int $unidadeId): array
    {
        if (! self::moduloAtivo()) {
            return [];
        }

        return DB::table('pdv_comandas')
            ->where('unidade_id', $unidadeId)
            ->whereIn('status', ['aberta', 'aguardando_pagamento'])
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public static function preContaHtml(int $comandaId): string
    {
        $data = self::comandaCompleta($comandaId);
        $com = $data['comanda'];
        $mesa = $com->mesa_id ? DB::table('mesas')->where('id', $com->mesa_id)->first() : null;
        $num = $mesa->numero_mesa ?? $mesa->nome_mesa ?? $com->mesa_id;
        $lines = ["<h2>Pré-conta — Mesa {$num}</h2>", '<p>Comanda #' . $com->id . '</p>'];
        foreach ($data['itens'] as $i) {
            $lines[] = '<div>' . htmlspecialchars($i->produto_nome) . ' · ' . $i->quantidade . ' × R$ ' . number_format((float) $i->preco_unitario, 2, ',', '.') . '</div>';
        }
        $lines[] = '<p><strong>Total: R$ ' . number_format((float) $com->valor_total, 2, ',', '.') . '</strong></p>';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $payload */
    public static function finalizarComanda(int $comandaId, array $payload, int $usuarioId): array
    {
        $forma = (string) ($payload['forma_pagamento'] ?? 'PDV');
        $errPg = PdvConfigSupport::validarDadosPagamento($forma, $payload);
        if ($errPg) {
            throw new \RuntimeException($errPg);
        }
        $errConta = ContaAssinadaSupport::validarParaPagamento($payload);
        if ($errConta) {
            throw new \RuntimeException($errConta);
        }
        if (ContaAssinadaSupport::isFormaContaAssinada($forma)) {
            $payload['sem_emissao'] = true;
            $payload['emitir_nota'] = false;
        }

        $data = self::comandaCompleta($comandaId);
        $com = $data['comanda'];
        $idem = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idem !== '' && Schema::hasColumn('vendas', 'idempotency_key')) {
            $existente = DB::table('vendas')->where('idempotency_key', mb_substr($idem, 0, 64))->first();
            if ($existente) {
                $venda = [
                    'venda_id' => (int) $existente->id,
                    'valor_liquido' => (float) $existente->valor_liquido,
                    'custo_total' => (float) ($existente->custo_total ?? 0),
                    'taxa_servico' => (float) ($existente->taxa_servico ?? 0),
                    'pagamento_cantor' => (float) ($existente->pagamento_cantor ?? 0),
                    'replayed' => true,
                ];
                $tentarEmissao = FiscalEmissaoService::deveEmitirParaPayload((int) $com->unidade_id, $payload);
                $venda = FiscalEmissaoService::anexarEmissaoAoResultado($venda, $tentarEmissao);

                return array_merge($venda, ['comanda_id' => $comandaId]);
            }
        }
        if (! in_array($com->status, ['aberta', 'aguardando_pagamento'], true)) {
            throw new \RuntimeException('Comanda já fechada.');
        }
        $itens = collect($data['itens'])->map(function ($i) {
            $row = [
                'produto_id' => (int) ($i->produto_id ?? 0),
                'quantidade' => (float) $i->quantidade,
                'preco_unitario' => (float) $i->preco_unitario,
                'desconto' => (float) $i->desconto,
            ];
            if (! empty($i->cardapio_produto_id)) {
                $row['cardapio_produto_id'] = (int) $i->cardapio_produto_id;
            }

            return $row;
        })->all();
        if (! count($itens)) {
            throw new \RuntimeException('Comanda sem itens.');
        }
        $vendaPayload = array_merge([
            'unidade_id' => (int) $com->unidade_id,
            'forma_pagamento' => $payload['forma_pagamento'] ?? 'PDV',
            'pdv_terminal' => $payload['pdv_terminal'] ?? 'PDV-MESA',
            'origem_venda' => $com->mesa_id ? 'mesa' : 'balcao',
            'mesa_id' => $com->mesa_id ? (int) $com->mesa_id : null,
            'comanda_id' => $comandaId,
            'reserva_mesa_id' => $com->reserva_mesa_id ? (int) $com->reserva_mesa_id : null,
            'observacao' => $payload['observacao'] ?? ('Comanda #' . $comandaId),
            'itens' => $itens,
        ], array_intersect_key($payload, array_flip([
            'idempotency_key',
            'emitir_nota',
            'sem_emissao',
            'conta_assinada_id',
            'pagamento_nsu',
            'pagamento_autorizacao',
            'pagamento_bandeira',
            'pagamento_parcelas',
            'pagamento_pix_id',
            'pagamento_pix_chave_id',
            'aplicar_taxa_servico',
            'aplicar_pagamento_cantor',
        ])));
        if (! VendaFiscalSupport::moduloAtivo()) {
            throw new \RuntimeException('Módulo fiscal de vendas indisponível.');
        }
        $venda = VendaFiscalSupport::finalizarVenda($vendaPayload, $usuarioId);
        ContaAssinadaSupport::registrarConsumoVenda($vendaPayload, $venda, $usuarioId);
        $tentarEmissao = empty($venda['replayed'])
            && FiscalEmissaoService::deveEmitirParaPayload((int) $com->unidade_id, $payload);
        $venda = FiscalEmissaoService::anexarEmissaoAoResultado($venda, $tentarEmissao);

        if (! empty($venda['replayed']) && (string) $com->status === 'fechada') {
            return array_merge($venda, ['comanda_id' => $comandaId]);
        }

        DB::table('pdv_comandas')->where('id', $comandaId)->update([
            'status' => 'fechada',
            'venda_id' => $venda['venda_id'],
            'fechada_em' => now(),
            'updated_at' => now(),
        ]);

        if ($com->mesa_id) {
            DB::table('mesas')->where('id', (int) $com->mesa_id)->update([
                'status' => Mesa::STATUS_LIVRE,
                'updated_at' => now(),
            ]);
        }
        if ($com->reserva_mesa_id && Schema::hasTable('reservas_mesas')) {
            DB::table('reservas_mesas')->where('id', (int) $com->reserva_mesa_id)->update([
                'conta_paga' => true,
                'valor_conta' => $venda['valor_liquido'],
                'conta_paga_em' => now(),
                'status' => ReservaMesa::STATUS_FINALIZADA,
                'updated_at' => now(),
            ]);
        }

        return array_merge($venda, ['comanda_id' => $comandaId]);
    }

    /** @param array<string, mixed> $payload */
    public static function vendaBalcao(array $payload, int $usuarioId): array
    {
        $forma = (string) ($payload['forma_pagamento'] ?? 'PDV');
        $errPg = PdvConfigSupport::validarDadosPagamento($forma, $payload);
        if ($errPg) {
            throw new \RuntimeException($errPg);
        }
        $errConta = ContaAssinadaSupport::validarParaPagamento($payload);
        if ($errConta) {
            throw new \RuntimeException($errConta);
        }
        if (ContaAssinadaSupport::isFormaContaAssinada($forma)) {
            $payload['sem_emissao'] = true;
            $payload['emitir_nota'] = false;
        }

        $payload['origem_venda'] = $payload['origem_venda'] ?? 'balcao';
        $payload['pdv_terminal'] = $payload['pdv_terminal'] ?? 'PDV-BALCAO';
        $unidadeId = (int) ($payload['unidade_id'] ?? 0);
        if ($unidadeId > 0 && isset($payload['itens']) && is_array($payload['itens'])) {
            $normalizados = CardapioComercialSupport::normalizarItensVenda($unidadeId, $payload['itens']);
            $payload['itens'] = array_map(static function (array $i) {
                $row = [
                    'produto_id' => $i['produto_id'],
                    'quantidade' => $i['quantidade'],
                    'preco_unitario' => $i['preco_unitario'],
                    'desconto' => $i['desconto'] ?? 0,
                ];
                if (! empty($i['cardapio_produto_id'])) {
                    $row['cardapio_produto_id'] = $i['cardapio_produto_id'];
                }

                return $row;
            }, $normalizados);
        }

        $venda = VendaFiscalSupport::finalizarVenda($payload, $usuarioId);
        ContaAssinadaSupport::registrarConsumoVenda($payload, $venda, $usuarioId);

        return FiscalEmissaoService::anexarEmissaoAoResultado(
            $venda,
            FiscalEmissaoService::deveEmitirParaPayload($unidadeId, $payload)
        );
    }
}
