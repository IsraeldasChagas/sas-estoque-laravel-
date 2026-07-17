<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryPedidoService
{
    public function __construct(
        private readonly DeliveryFreteService $freteService,
        private readonly DeliveryPedidoStatusService $statusService,
    ) {}

    public function montarItens(int $unidadeId, array $itens): array
    {
        if ($itens === []) {
            throw ValidationException::withMessages(['itens' => 'Informe ao menos um item.']);
        }

        $linhas = [];
        $subtotal = 0.0;

        foreach ($itens as $index => $item) {
            $produtoId = (int) ($item['produto_id'] ?? 0);
            $quantidade = max(0.001, (float) ($item['quantidade'] ?? 1));
            $produto = DB::table('dlv_produtos')
                ->where('id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('ativo', 1)
                ->first();

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
        $montagem = $this->montarItens($unidadeId, $payload['itens'] ?? []);
        $totais = $this->calcularTotais($unidadeId, $payload, $montagem);
        $agora = now();
        $token = Str::random(40);

        return (int) DB::transaction(function () use ($unidadeId, $payload, $usuarioId, $montagem, $totais, $agora, $token) {
            $id = DB::table('dlv_pedidos')->insertGetId([
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
                'pagamento_forma' => $payload['pagamento_forma'] ?? null,
                'pagamento_status' => $payload['pagamento_status'] ?? 'pendente',
                'subtotal' => $totais['subtotal'],
                'frete_valor' => $totais['frete_valor'],
                'total' => $totais['total'],
                'entregador_id' => $payload['entregador_id'] ?? null,
                'entregador_token' => $token,
                'observacoes' => $payload['observacoes'] ?? null,
                'usuario_id' => $usuarioId,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

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
            DB::table('dlv_pedidos')->where('id', $pedido->id)->update([
                'status' => $novoStatus,
                'updated_at' => now(),
            ]);
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

        return [
            'id' => (int) $pedido->id,
            'unidade_id' => (int) $pedido->unidade_id,
            'codigo_publico' => (string) $pedido->codigo_publico,
            'status' => (string) $pedido->status,
            'canal' => (string) $pedido->canal,
            'fulfillment' => (string) $pedido->fulfillment,
            'cliente_nome' => (string) $pedido->cliente_nome,
            'cliente_telefone' => $pedido->cliente_telefone,
            'cliente_whatsapp' => $pedido->cliente_whatsapp,
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
            'subtotal' => (float) $pedido->subtotal,
            'frete_valor' => (float) $pedido->frete_valor,
            'total' => (float) $pedido->total,
            'entregador_id' => $pedido->entregador_id !== null ? (int) $pedido->entregador_id : null,
            'entregador_token' => $pedido->entregador_token,
            'observacoes' => $pedido->observacoes,
            'usuario_id' => $pedido->usuario_id !== null ? (int) $pedido->usuario_id : null,
            'created_at' => $pedido->created_at,
            'updated_at' => $pedido->updated_at,
            'itens' => $itens,
            'historico' => $historico,
        ];
    }

    private function validarOpcoes(object $produto, mixed $opcoes, int $unidadeId): array
    {
        $opcoes = is_array($opcoes) ? $opcoes : [];
        $adicionaisSelecionados = collect($opcoes['adicionais'] ?? []);
        $retiradas = collect($opcoes['retiradas'] ?? []);

        if (! (bool) $produto->permite_adicionais && ($adicionaisSelecionados->isNotEmpty() || $retiradas->isNotEmpty())) {
            throw ValidationException::withMessages(['opcoes' => 'Este produto não permite adicionais/retiradas.']);
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

            if ($qtdEscolhas < $min) {
                throw ValidationException::withMessages(['opcoes.adicionais' => "Selecione ao menos {$min} adicional(is)."]);
            }
            if ($max !== null && $qtdEscolhas > $max) {
                throw ValidationException::withMessages(['opcoes.adicionais' => "Selecione no máximo {$max} adicional(is)."]);
            }
        }

        $snapshotRetiradas = [];
        if ($retiradas->isNotEmpty()) {
            $maxRetirar = $produto->max_ingredientes_retirar !== null ? (int) $produto->max_ingredientes_retirar : null;
            if ($maxRetirar !== null && $retiradas->count() > $maxRetirar) {
                throw ValidationException::withMessages(['opcoes.retiradas' => "Retire no máximo {$maxRetirar} ingrediente(s)."]);
            }
            foreach ($retiradas as $idx => $ret) {
                $ingredienteId = (int) ($ret['id'] ?? $ret['ingrediente_id'] ?? 0);
                $ing = DB::table('dlv_produto_ingredientes')
                    ->where('id', $ingredienteId)
                    ->where('produto_id', $produto->id)
                    ->first();
                if (! $ing) {
                    throw ValidationException::withMessages(["opcoes.retiradas.$idx" => 'Ingrediente inválido.']);
                }
                $snapshotRetiradas[] = [
                    'id' => (int) $ing->id,
                    'nome' => (string) $ing->nome,
                ];
            }
        }

        return [
            'preco_adicionais' => round($precoAdicionais, 2),
            'snapshot' => [
                'adicionais' => $snapshotAdicionais,
                'retiradas' => $snapshotRetiradas,
            ],
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
