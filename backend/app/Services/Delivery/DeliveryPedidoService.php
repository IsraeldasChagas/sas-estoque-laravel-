<?php

namespace App\Services\Delivery;

use App\Support\Delivery\DeliveryCupomPedido;
use App\Support\Delivery\DeliveryLojaCheckoutHelper;
use App\Support\Delivery\DeliveryMediaUrl;
use App\Support\Delivery\DeliveryPedidoPresenter;
use App\Support\Delivery\DeliveryWhatsAppHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryPedidoService
{
    public function __construct(
        private readonly DeliveryFreteService $freteService,
        private readonly DeliveryPedidoStatusService $statusService,
    ) {}

    public function montarItens(int $unidadeId, array $itens, bool $publico = false): array
    {
        if ($itens === []) {
            throw ValidationException::withMessages(['itens' => 'Informe ao menos um item.']);
        }

        $linhas = [];
        $subtotal = 0.0;

        foreach ($itens as $index => $item) {
            $produtoId = (int) ($item['produto_id'] ?? 0);
            $quantidade = (float) ($item['quantidade'] ?? 1);
            if ($quantidade <= 0 || ($publico && (int) $quantidade != $quantidade)) {
                throw ValidationException::withMessages(["itens.$index.quantidade" => 'Quantidade inválida.']);
            }
            $produtoQuery = DB::table('dlv_produtos')
                ->where('id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('ativo', 1);
            if ($publico) {
                $produtoQuery->where('visivel_loja', 1)
                    ->where(function ($query) use ($unidadeId) {
                        $query->whereNull('categoria_id')->orWhereExists(fn ($category) => $category
                            ->selectRaw('1')->from('dlv_categorias')
                            ->whereColumn('dlv_categorias.id', 'dlv_produtos.categoria_id')
                            ->where('dlv_categorias.unidade_id', $unidadeId)
                            ->where('dlv_categorias.ativo', 1));
                    })->lockForUpdate();
            }
            $produto = $produtoQuery->first();

            if (! $produto) {
                throw ValidationException::withMessages([
                    "itens.$index.produto_id" => 'Produto delivery inválido ou inativo.',
                ]);
            }
            $opcoes = $this->validarOpcoes($produto, $item['opcoes'] ?? [], $unidadeId);
            $precoUnitario = round((float) $produto->preco, 2);
            $precoAdicionais = round((float) $opcoes['preco_adicionais'], 2);
            $linhaSubtotal = round(($precoUnitario + $precoAdicionais) * $quantidade, 2);
            $subtotal += $linhaSubtotal;

            $linhas[] = [
                'produto_id' => (int) $produto->id,
                'nome_produto' => (string) $produto->nome,
                'quantidade' => $quantidade,
                'preco_unitario' => $precoUnitario,
                'preco_adicionais' => $precoAdicionais,
                'subtotal' => $linhaSubtotal,
                'opcoes_json' => $opcoes['snapshot'],
                'ordem' => $index,
            ];
        }

        return [
            'linhas' => $linhas,
            'subtotal' => round($subtotal, 2),
        ];
    }

    public function calcularTotais(int $unidadeId, array $payload, array $montagem): array
    {
        $fulfillment = strtolower(trim((string) ($payload['fulfillment'] ?? 'entrega')));
        $frete = $this->freteService->calcular($unidadeId, [
            'fulfillment' => $fulfillment,
            'subtotal' => $montagem['subtotal'],
            'cep' => $payload['endereco_cep'] ?? ($payload['cep'] ?? null),
            'endereco_cep' => $payload['endereco_cep'] ?? null,
            'endereco_rua' => $payload['endereco_rua'] ?? null,
            'endereco_numero' => $payload['endereco_numero'] ?? null,
            'endereco_bairro' => $payload['endereco_bairro'] ?? null,
            'endereco_cidade' => $payload['endereco_cidade'] ?? null,
            'endereco_uf' => $payload['endereco_uf'] ?? null,
            'chuva' => $payload['chuva'] ?? null,
        ]);

        if (! empty($frete['bloqueado'])) {
            throw ValidationException::withMessages(['cep' => $frete['mensagem'] ?? 'Entrega indisponível para o CEP.']);
        }

        $freteValor = round((float) $frete['frete_valor'], 2);
        $total = round($montagem['subtotal'] + $freteValor, 2);

        return [
            'subtotal' => $montagem['subtotal'],
            'frete_valor' => $freteValor,
            'total' => $total,
            'frete' => $frete,
        ];
    }

    public function criar(int $unidadeId, array $payload, ?int $usuarioId): int
    {
        $agora = now();
        $entregadorToken = Str::random(40);
        $clienteToken = bin2hex(random_bytes(32));
        $publico = strtolower((string) ($payload['canal'] ?? 'admin')) === 'loja';

        return (int) DB::transaction(function () use ($unidadeId, $payload, $usuarioId, $agora, $entregadorToken, $clienteToken, $publico) {
            $montagem = $this->montarItens($unidadeId, $payload['itens'] ?? [], $publico);
            $totais = $this->calcularTotais($unidadeId, $payload, $montagem);
            $pedidoData = [
                'unidade_id' => $unidadeId,
                'codigo_publico' => 'TMP-'.$agora->format('YmdHis').'-'.Str::lower(Str::random(4)),
                'status' => 'pendente_loja',
                'canal' => (string) ($payload['canal'] ?? 'admin'),
                'fulfillment' => strtolower(trim((string) ($payload['fulfillment'] ?? 'entrega'))),
                'cliente_nome' => (string) $payload['cliente_nome'],
                'cliente_telefone' => $payload['cliente_telefone'] ?? null,
                'cliente_whatsapp' => $payload['cliente_whatsapp'] ?? null,
                'endereco_texto' => $payload['endereco_texto'] ?? null,
                'endereco_cep' => isset($payload['endereco_cep']) ? preg_replace('/\D+/', '', (string) $payload['endereco_cep']) : null,
                'endereco_rua' => $payload['endereco_rua'] ?? null,
                'endereco_numero' => $payload['endereco_numero'] ?? null,
                'endereco_bairro' => $payload['endereco_bairro'] ?? null,
                'endereco_cidade' => $payload['endereco_cidade'] ?? null,
                'endereco_uf' => $payload['endereco_uf'] ?? null,
                'endereco_complemento' => $payload['endereco_complemento'] ?? null,
                'pagamento_forma' => isset($payload['pagamento_forma'])
                    ? DeliveryLojaCheckoutHelper::normalizarFormaPagamento((string) $payload['pagamento_forma'])
                    : null,
                'pagamento_status' => $payload['pagamento_status'] ?? 'pendente',
                'subtotal' => $totais['subtotal'],
                'frete_valor' => $totais['frete_valor'],
                'total' => $totais['total'],
                'entregador_id' => $payload['entregador_id'] ?? null,
                'entregador_token' => $entregadorToken,
                'observacoes' => $payload['observacoes'] ?? null,
                'usuario_id' => $usuarioId,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
            if (Schema::hasColumn('dlv_pedidos', 'cliente_email')) {
                $pedidoData['cliente_email'] = $payload['cliente_email'] ?? null;
            }
            if (Schema::hasColumn('dlv_pedidos', 'cliente_token')) {
                $pedidoData['cliente_token'] = $clienteToken;
            }
            if (Schema::hasColumn('dlv_pedidos', 'pagamento_troco_para') && array_key_exists('pagamento_troco_para', $payload)) {
                $pedidoData['pagamento_troco_para'] = $payload['pagamento_troco_para'];
            }
            if (Schema::hasColumn('dlv_pedidos', 'estoque_baixado_em')) {
                $pedidoData['estoque_baixado_em'] = $publico ? $agora : null;
                $pedidoData['estoque_restaurado_em'] = null;
            }
            if (Schema::hasColumn('dlv_pedidos', 'participa_fidelidade')) {
                $pedidoData['participa_fidelidade'] = filter_var($payload['fidelidade_quero'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $id = DB::table('dlv_pedidos')->insertGetId($pedidoData);

            $codigo = 'DLV-'.str_pad((string) $unidadeId, 3, '0', STR_PAD_LEFT).'-'.$agora->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            DB::table('dlv_pedidos')->where('id', $id)->update(['codigo_publico' => $codigo]);

            foreach ($montagem['linhas'] as $linha) {
                DB::table('dlv_pedido_itens')->insert([
                    'pedido_id' => $id,
                    'produto_id' => $linha['produto_id'],
                    'nome_produto' => $linha['nome_produto'],
                    'quantidade' => $linha['quantidade'],
                    'preco_unitario' => $linha['preco_unitario'],
                    'preco_adicionais' => $linha['preco_adicionais'],
                    'subtotal' => $linha['subtotal'],
                    'opcoes_json' => json_encode($linha['opcoes_json'], JSON_UNESCAPED_UNICODE),
                    'ordem' => $linha['ordem'],
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            $this->registrarHistorico($id, null, 'pendente_loja', 'criado', ['total' => $totais['total']], $usuarioId);

            return $id;
        });
    }

    public function alterarStatus(object $pedido, string $novoStatus, ?int $usuarioId, ?string $detalhe = null): object
    {
        $novoStatus = strtolower(trim($novoStatus));
        $this->statusService->validarTransicao((string) $pedido->status, $novoStatus);

        DB::transaction(function () use ($pedido, $novoStatus, $usuarioId, $detalhe) {
            $atual = DB::table('dlv_pedidos')->where('id', $pedido->id)->lockForUpdate()->first();
            if (! $atual) {
                return;
            }
            $update = [
                'status' => $novoStatus,
                'updated_at' => now(),
            ];
            DB::table('dlv_pedidos')->where('id', $pedido->id)->update($update);
            $this->registrarHistorico(
                (int) $pedido->id,
                (string) $pedido->status,
                $novoStatus,
                'status_alterado',
                $detalhe ? ['detalhe' => $detalhe] : null,
                $usuarioId
            );
        });

        return DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
    }

    public function confirmarPagamentoPix(object $pedido, ?int $usuarioId, string $origem = 'operador'): object
    {
        if (! DeliveryPedidoPresenter::isPix($pedido)) {
            throw ValidationException::withMessages(['pagamento' => 'Este pedido não é PIX.']);
        }

        return $this->confirmarPagamentoGateway($pedido, $usuarioId, $origem);
    }

    public function confirmarPagamentoGateway(object $pedido, ?int $usuarioId, string $origem = 'operador'): object
    {
        if (! DeliveryPedidoPresenter::isPagamentoGateway($pedido)) {
            throw ValidationException::withMessages(['pagamento' => 'Este pedido não usa confirmação de gateway.']);
        }
        if (DeliveryPedidoPresenter::pagamentoGatewayPago($pedido)) {
            throw ValidationException::withMessages(['pagamento' => 'Pagamento já confirmado para este pedido.']);
        }

        $agora = now();
        $update = [
            'pagamento_status' => DeliveryPedidoPresenter::PAGAMENTO_STATUS_PAGO,
            'updated_at' => $agora,
        ];
        if (Schema::hasColumn('dlv_pedidos', 'pagamento_confirmado_em')) {
            $update['pagamento_confirmado_em'] = $agora;
        }
        if (Schema::hasColumn('dlv_pedidos', 'pagamento_confirmado_origem')) {
            $update['pagamento_confirmado_origem'] = in_array($origem, ['operador', 'webhook', 'manual'], true) ? $origem : 'operador';
        }
        if (Schema::hasColumn('dlv_pedidos', 'pagamento_gateway_status')) {
            $update['pagamento_gateway_status'] = 'approved';
        }
        DB::table('dlv_pedidos')->where('id', $pedido->id)->update($update);
        $this->registrarHistorico(
            (int) $pedido->id,
            (string) ($pedido->pagamento_status ?? DeliveryPedidoPresenter::PAGAMENTO_STATUS_PENDENTE),
            DeliveryPedidoPresenter::PAGAMENTO_STATUS_PAGO,
            'pagamento_confirmado',
            ['forma' => (string) $pedido->pagamento_forma, 'origem' => $origem],
            $usuarioId
        );

        return DB::table('dlv_pedidos')->where('id', $pedido->id)->first();
    }

    public function validarAceitePix(object $pedido, ?object $config): void
    {
        if (! DeliveryPedidoPresenter::bloqueiaAceitePorPix($pedido, $config)) {
            return;
        }

        throw ValidationException::withMessages([
            'pagamento' => 'Confirme o pagamento online antes de aceitar o pedido.',
        ]);
    }

    public function completo(object $pedido): array
    {
        $itens = DB::table('dlv_pedido_itens')->where('pedido_id', $pedido->id)->orderBy('ordem')->orderBy('id')->get()
            ->map(function ($item) {
                $opcoes = $item->opcoes_json;
                if (is_string($opcoes)) {
                    $opcoes = json_decode($opcoes, true);
                }

                return [
                    'id' => (int) $item->id,
                    'produto_id' => $item->produto_id !== null ? (int) $item->produto_id : null,
                    'nome_produto' => (string) $item->nome_produto,
                    'quantidade' => (float) $item->quantidade,
                    'preco_unitario' => (float) $item->preco_unitario,
                    'preco_adicionais' => (float) $item->preco_adicionais,
                    'subtotal' => (float) $item->subtotal,
                    'opcoes' => $opcoes ?: [],
                    'ordem' => (int) $item->ordem,
                ];
            })->values();

        $historico = DB::table('dlv_pedido_historico')->where('pedido_id', $pedido->id)->orderBy('id')->get()
            ->map(function ($row) {
                $detalhes = $row->detalhes;
                if (is_string($detalhes)) {
                    $detalhes = json_decode($detalhes, true);
                }

                return [
                    'id' => (int) $row->id,
                    'status_anterior' => $row->status_anterior,
                    'status_novo' => $row->status_novo,
                    'acao' => $row->acao,
                    'detalhes' => $detalhes,
                    'usuario_id' => $row->usuario_id !== null ? (int) $row->usuario_id : null,
                    'created_at' => $row->created_at,
                ];
            })->values();

        $base = [
            'id' => (int) $pedido->id,
            'unidade_id' => (int) $pedido->unidade_id,
            'codigo_publico' => (string) $pedido->codigo_publico,
            'status' => (string) $pedido->status,
            'status_rotulo' => DeliveryPedidoPresenter::rotuloStatus($pedido->status ?? null),
            'canal' => (string) $pedido->canal,
            'fulfillment' => (string) $pedido->fulfillment,
            'fulfillment_rotulo' => DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null),
            'cliente_nome' => (string) $pedido->cliente_nome,
            'cliente_telefone' => $pedido->cliente_telefone,
            'cliente_whatsapp' => $pedido->cliente_whatsapp,
            'cliente_email' => $pedido->cliente_email ?? null,
            'cliente_whatsapp_url' => DeliveryWhatsAppHelper::urlContato($pedido->cliente_whatsapp ?: $pedido->cliente_telefone),
            'endereco' => [
                'texto' => $pedido->endereco_texto,
                'cep' => $pedido->endereco_cep,
                'rua' => $pedido->endereco_rua,
                'numero' => $pedido->endereco_numero,
                'bairro' => $pedido->endereco_bairro,
                'cidade' => $pedido->endereco_cidade,
                'uf' => $pedido->endereco_uf,
                'complemento' => $pedido->endereco_complemento,
            ],
            'pagamento_forma' => $pedido->pagamento_forma,
            'pagamento_status' => $pedido->pagamento_status,
            'pagamento_confirmado_em' => $pedido->pagamento_confirmado_em ?? null,
            'pagamento_troco_para' => isset($pedido->pagamento_troco_para) ? (float) $pedido->pagamento_troco_para : null,
            'pagamento_descricao' => DeliveryPedidoPresenter::descricaoPagamento($pedido),
            'pagamento_status_rotulo' => DeliveryPedidoPresenter::rotuloPagamentoStatus($pedido),
            'pix_pendente' => DeliveryPedidoPresenter::pixPendenteConfirmacao($pedido),
            'pix_pago' => DeliveryPedidoPresenter::isPixPago($pedido),
            'cartao_online_pendente' => DeliveryPedidoPresenter::cartaoOnlinePendente($pedido),
            'cartao_online_pago' => DeliveryPedidoPresenter::isCartaoOnlinePago($pedido),
            'pagamento_checkout_url' => $pedido->pagamento_checkout_url ?? null,
            'subtotal' => (float) $pedido->subtotal,
            'frete_valor' => (float) $pedido->frete_valor,
            'total' => (float) $pedido->total,
            'entregador_id' => $pedido->entregador_id !== null ? (int) $pedido->entregador_id : null,
            'entregador_token' => $pedido->entregador_token,
            'entregador_pode_registrar' => DeliveryPedidoPresenter::entregadorPodeRegistrarResultado($pedido->status ?? null),
            'observacoes' => $pedido->observacoes,
            'usuario_id' => $pedido->usuario_id !== null ? (int) $pedido->usuario_id : null,
            'created_at' => $pedido->created_at,
            'updated_at' => $pedido->updated_at,
            'itens' => $itens,
            'historico' => $historico,
        ];

        return array_merge($base, $this->metaOperacao($pedido));
    }

    /** @return array<string, mixed> */
    public function metaOperacao(object $pedido): array
    {
        $config = DB::table('dlv_loja_config')->where('unidade_id', $pedido->unidade_id)->first();
        $itensDb = DB::table('dlv_pedido_itens')->where('pedido_id', $pedido->id)->orderBy('ordem')->orderBy('id')->get();
        $slug = trim((string) ($config->slug ?? ''));
        $isEntrega = strtolower(trim((string) ($pedido->fulfillment ?? 'entrega'))) === 'entrega';

        $urlEntregador = null;
        if ($isEntrega && $slug !== '' && ! empty($pedido->entregador_token)) {
            $urlEntregador = route('delivery.public.entregador.show', [
                'slug' => $slug,
                'codigo' => $pedido->codigo_publico,
                'token' => $pedido->entregador_token,
            ], absolute: true);
        }

        $entregadores = [];
        if ($isEntrega && Schema::hasTable('dlv_entregadores')) {
            $entregadores = DB::table('dlv_entregadores')
                ->where('unidade_id', $pedido->unidade_id)
                ->where('ativo', 1)
                ->orderBy('ordem')
                ->orderBy('nome')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'nome' => (string) $row->nome,
                    'whatsapp' => $row->whatsapp,
                    'telefone' => $row->telefone,
                    'foto_path' => $row->foto_path ?? null,
                    'foto_url' => DeliveryMediaUrl::fromPublicPath($row->foto_path ?? null),
                    'whatsapp_url' => DeliveryWhatsAppHelper::urlContato($row->whatsapp ?: $row->telefone),
                ])->values()->all();
        }

        return [
            'loja_slug' => $slug !== '' ? $slug : null,
            'loja_nome' => $config->nome_loja ?? null,
            'confirmar_pedidos' => (bool) ($config->confirmar_pedidos ?? true),
            'exigir_pix_confirmado' => DeliveryPedidoPresenter::exigirPixConfirmado($config),
            'pagamento_bloqueia_aceite' => DeliveryPedidoPresenter::bloqueiaAceitePorPix($pedido, $config),
            'pode_confirmar_pix' => DeliveryPedidoPresenter::pagamentoGatewayPendente($pedido),
            'pode_confirmar_pagamento' => DeliveryPedidoPresenter::pagamentoGatewayPendente($pedido),
            'impressao_habilitada' => true,
            'url_imprimir' => '/delivery/pedidos/'.(int) $pedido->id.'/imprimir',
            'url_entregador' => $urlEntregador,
            'cupom_whatsapp_url' => $config
                ? DeliveryCupomPedido::urlWhatsAppCupom($pedido, $config, $itensDb)
                : null,
            'entregadores' => $entregadores,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function serializarPedidosPendentesPoll(int $unidadeId): array
    {
        return DB::table('dlv_pedidos')
            ->where('unidade_id', $unidadeId)
            ->where('status', 'pendente_loja')
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($pedido) => $this->serializarPedidoPendentePoll($pedido))
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function proximoPedidoPendentePoll(int $unidadeId): ?array
    {
        $proximo = DB::table('dlv_pedidos')
            ->where('unidade_id', $unidadeId)
            ->where('status', 'pendente_loja')
            ->orderBy('created_at')
            ->first();

        return $proximo ? $this->serializarPedidoPendentePoll($proximo) : null;
    }

    /** @return array<string, mixed> */
    private function serializarPedidoPendentePoll(object $pedido): array
    {
        $itens = DB::table('dlv_pedido_itens')
            ->where('pedido_id', $pedido->id)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => [
                'nome' => (string) $item->nome_produto,
                'qtd' => (float) $item->quantidade,
            ])->values()->all();

        $createdAt = $pedido->created_at
            ? \Illuminate\Support\Carbon::parse($pedido->created_at)->format('d/m/Y H:i')
            : '';

        return [
            'id' => (int) $pedido->id,
            'codigo_publico' => (string) $pedido->codigo_publico,
            'cliente_nome' => (string) $pedido->cliente_nome,
            'total_fmt' => 'R$ '.number_format((float) $pedido->total, 2, ',', '.'),
            'tipo_entrega' => DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null),
            'fulfillment_rotulo' => DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null),
            'created_at' => $createdAt,
            'pendente_post_url' => '/delivery/pedidos/'.(int) $pedido->id.'/pendente',
            'show_url' => null,
            'itens' => $itens,
        ];
    }

    private function validarOpcoes(object $produto, mixed $opcoes, int $unidadeId): array
    {
        $opcoes = is_array($opcoes) ? $opcoes : [];
        $adicionaisSelecionados = collect($opcoes['adicionais'] ?? []);
        $retiradas = collect($opcoes['retiradas'] ?? []);

        $temIngredientes = DB::table('dlv_produto_ingredientes')
            ->where('produto_id', $produto->id)
            ->exists();
        $temAdicionaisVinculados = DB::table('dlv_produto_adicional')
            ->where('produto_id', $produto->id)
            ->exists();
        $permiteAdicionaisPagos = (bool) $produto->permite_adicionais || $temAdicionaisVinculados;

        if ($adicionaisSelecionados->isNotEmpty() && ! $permiteAdicionaisPagos) {
            throw ValidationException::withMessages(['opcoes' => 'Este produto não permite adicionais.']);
        }

        if ($retiradas->isNotEmpty() && ! $temIngredientes) {
            throw ValidationException::withMessages(['opcoes' => 'Este produto não permite personalização de ingredientes.']);
        }

        $snapshotAdicionais = [];
        $precoAdicionais = 0.0;

        if ($adicionaisSelecionados->isNotEmpty()) {
            $idsPermitidos = DB::table('dlv_produto_adicional')
                ->where('produto_id', $produto->id)
                ->pluck('adicional_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $min = (int) ($produto->acrescimo_escolhas_min ?? 0);
            $max = $produto->acrescimo_escolhas_max !== null ? (int) $produto->acrescimo_escolhas_max : null;
            $qtdEscolhas = 0;

            foreach ($adicionaisSelecionados as $idx => $sel) {
                $adicionalId = (int) ($sel['id'] ?? $sel['adicional_id'] ?? 0);
                $qtd = max(1, (int) ($sel['quantidade'] ?? 1));
                if (! in_array($adicionalId, $idsPermitidos, true)) {
                    throw ValidationException::withMessages(["opcoes.adicionais.$idx" => 'Adicional não vinculado ao produto.']);
                }
                $adicional = DB::table('dlv_adicionais')
                    ->where('id', $adicionalId)
                    ->where('unidade_id', $unidadeId)
                    ->where('ativo', 1)
                    ->first();
                if (! $adicional) {
                    throw ValidationException::withMessages(["opcoes.adicionais.$idx" => 'Adicional inválido.']);
                }
                $preco = round((float) $adicional->preco, 2);
                $precoAdicionais += $preco * $qtd;
                $qtdEscolhas += $qtd;
                $snapshotAdicionais[] = [
                    'id' => (int) $adicional->id,
                    'nome' => (string) $adicional->nome,
                    'tipo' => (string) $adicional->tipo,
                    'preco' => $preco,
                    'quantidade' => $qtd,
                ];
            }

            if ($max !== null && $qtdEscolhas > $max) {
                throw ValidationException::withMessages(['opcoes.adicionais' => "Selecione no máximo {$max} adicional(is)."]);
            }
        }
        $min = (int) ($produto->acrescimo_escolhas_min ?? 0);
        if ($adicionaisSelecionados->sum(fn ($sel) => max(1, (int) ($sel['quantidade'] ?? 1))) < $min) {
            throw ValidationException::withMessages(['opcoes.adicionais' => "Selecione ao menos {$min} adicional(is)."]);
        }

        $snapshotRetiradas = [];
        if ($retiradas->isNotEmpty()) {
            $maxRetirar = $produto->max_ingredientes_retirar !== null ? (int) $produto->max_ingredientes_retirar : null;
            $qtdRetiradas = 0;
            $porIngrediente = [];

            foreach ($retiradas as $idx => $ret) {
                $ingredienteId = (int) ($ret['id'] ?? $ret['ingrediente_id'] ?? 0);
                $qtd = max(1, (int) ($ret['quantidade'] ?? 1));
                if (! isset($porIngrediente[$ingredienteId])) {
                    $porIngrediente[$ingredienteId] = 0;
                }
                $porIngrediente[$ingredienteId] += $qtd;
            }

            foreach ($porIngrediente as $ingredienteId => $qtd) {
                $ing = DB::table('dlv_produto_ingredientes')
                    ->where('id', $ingredienteId)
                    ->where('produto_id', $produto->id)
                    ->first();
                if (! $ing) {
                    throw ValidationException::withMessages(['opcoes.retiradas' => 'Ingrediente inválido.']);
                }
                $qtdRetiradas += $qtd;
                $snapshotRetiradas[] = [
                    'id' => (int) $ing->id,
                    'nome' => (string) $ing->nome,
                    'quantidade' => $qtd,
                ];
            }

            if ($maxRetirar !== null && $qtdRetiradas > $maxRetirar) {
                throw ValidationException::withMessages(['opcoes.retiradas' => "Selecione no máximo {$maxRetirar} opção(ões)."]);
            }
            if ($maxRetirar !== null && $qtdRetiradas < $maxRetirar && $adicionaisSelecionados->isEmpty()) {
                throw ValidationException::withMessages(['opcoes.retiradas' => "Selecione exatamente {$maxRetirar} opção(ões)."]);
            }
        }

        return [
            'preco_adicionais' => round($precoAdicionais, 2),
            'snapshot' => array_filter([
                'adicionais' => $snapshotAdicionais,
                'retiradas' => $snapshotRetiradas,
                'observacao' => ($obs = trim((string) ($opcoes['observacao'] ?? ''))) !== ''
                    ? mb_substr($obs, 0, 500)
                    : null,
                'nota_produto' => ($nota = (int) ($opcoes['nota_produto'] ?? 0)) >= 1 && $nota <= 5
                    ? $nota
                    : null,
            ], fn ($v) => $v !== null && $v !== []),
        ];
    }

    private function registrarHistorico(int $pedidoId, ?string $anterior, string $novo, string $acao, ?array $detalhes, ?int $usuarioId): void
    {
        DB::table('dlv_pedido_historico')->insert([
            'pedido_id' => $pedidoId,
            'status_anterior' => $anterior,
            'status_novo' => $novo,
            'acao' => $acao,
            'detalhes' => $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null,
            'usuario_id' => $usuarioId,
            'created_at' => now(),
        ]);
    }
}
