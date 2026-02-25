<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service central para registrar entrada de estoque com geração automática de lote.
 * Usado pelo dashboard (entrada manual) e pela lista de compras.
 */
class EntradaEstoqueService
{
    /**
     * Registra entrada de estoque: cria/atualiza lote, movimentação e saldo.
     *
     * @param array $dados
     *   - produto_id (required)
     *   - unidade_id (required)
     *   - quantidade (required)
     *   - custo_unitario (required)
     *   - usuario_id (required)
     *   - numero_lote (opcional, auto-gerado se vazio)
     *   - data_fabricacao (opcional)
     *   - data_validade (opcional)
     *   - local_id (opcional)
     *   - motivo (opcional)
     *   - observacao (opcional)
     *   - origem (opcional): 'DASHBOARD' | 'LISTA_COMPRAS'
     *   - data_movimento (opcional, default now)
     *
     * @param bool $useTransaction Se false, não inicia transação (para uso dentro de outra transação)
     * @return array{lote: object, movimentacao: object, codigo_lote: string}
     * @throws \Exception
     */
    public function registrarEntrada(array $dados, bool $useTransaction = true): array
    {
        $executar = function () use ($dados) {
            $produtoId = (int) ($dados['produto_id'] ?? 0);
            $unidadeId = (int) ($dados['unidade_id'] ?? 0);
            $quantidade = (float) ($dados['quantidade'] ?? $dados['qtd'] ?? 0);
            $custoUnitario = (float) ($dados['custo_unitario'] ?? 0);
            $usuarioId = (int) ($dados['usuario_id'] ?? 0);
            $numeroLote = isset($dados['numero_lote']) ? trim((string) $dados['numero_lote']) : '';
            $dataValidade = $dados['data_validade'] ?? null;
            $dataFabricacao = $dados['data_fabricacao'] ?? null;
            $localId = isset($dados['local_id']) ? (int) $dados['local_id'] : null;
            $motivo = $dados['motivo'] ?? 'Entrada de estoque';
            $observacao = $dados['observacao'] ?? null;
            $origem = $dados['origem'] ?? 'DASHBOARD';
            $dataMovimento = $dados['data_movimento'] ?? now();

            if ($produtoId <= 0 || $unidadeId <= 0) {
                throw new \InvalidArgumentException('produto_id e unidade_id são obrigatórios.');
            }
            if ($quantidade <= 0) {
                throw new \InvalidArgumentException('Quantidade deve ser maior que zero.');
            }
            if ($custoUnitario < 0) {
                throw new \InvalidArgumentException('Custo unitário não pode ser negativo.');
            }
            if ($usuarioId <= 0) {
                throw new \InvalidArgumentException('usuario_id é obrigatório.');
            }

            // 1. Buscar produto e validar
            $produto = DB::table('produtos')->where('id', $produtoId)->first();
            if (!$produto) {
                throw new \RuntimeException('Produto não encontrado.');
            }
            if (isset($produto->ativo) && $produto->ativo != 1) {
                throw new \RuntimeException('Produto inativo. Ative o produto antes de registrar entrada.');
            }

            // 2. Validar unidade
            $unidade = DB::table('unidades')->where('id', $unidadeId)->first();
            if (!$unidade) {
                throw new \RuntimeException('Unidade não encontrada.');
            }

            // 3. Gerar numero_lote se vazio
            if ($numeroLote === '') {
                $numeroLote = 'ENT-' . $produtoId . '-' . $unidadeId . '-' . now()->format('YmdHis');
            }

            $unidadeBase = $this->normalizarUnidadeBase($produto->unidade_base ?? 'UND');

            // 4. Buscar ou criar local
            if (!$localId) {
                $localPadrao = DB::table('locais')->where('unidade_id', $unidadeId)->first();
                if ($localPadrao) {
                    $localId = $localPadrao->id;
                } else {
                    $localId = DB::table('locais')->insertGetId([
                        'nome' => 'Depósito Principal',
                        'unidade_id' => $unidadeId,
                        'tipo' => 'DEPOSITO',
                        'ativo' => 1,
                    ]);
                }
            }

            // 5. Buscar ou criar lote
            $lote = DB::table('lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('numero_lote', $numeroLote)
                ->first();

            if ($lote) {
                $loteId = $lote->id;
                $updateData = [];
                if ($dataValidade && (!$lote->data_validade || $dataValidade < $lote->data_validade)) {
                    $updateData['data_validade'] = $dataValidade;
                }
                if ($custoUnitario > 0 && (!$lote->custo_unitario || $custoUnitario != $lote->custo_unitario)) {
                    $updateData['custo_unitario'] = $custoUnitario;
                }
                if (!empty($updateData)) {
                    DB::table('lotes')->where('id', $loteId)->update($updateData);
                }
            } else {
                $loteId = DB::table('lotes')->insertGetId([
                    'produto_id' => $produtoId,
                    'unidade_id' => $unidadeId,
                    'numero_lote' => $numeroLote,
                    'qtd_atual' => $quantidade,
                    'unidade' => $unidadeBase,
                    'custo_unitario' => $custoUnitario,
                    'data_fabricacao' => $dataFabricacao,
                    'data_validade' => $dataValidade,
                    'local_id' => $localId,
                    'ativo' => 1,
                    'criado_em' => now(),
                ]);
            }

            // 6. Atualizar ou criar stock_lotes
            $stockLote = DB::table('stock_lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('codigo_lote', $numeroLote)
                ->first();

            if ($stockLote) {
                $novaQuantidade = $stockLote->quantidade + $quantidade;
                $custoMedio = (($stockLote->quantidade * $stockLote->custo_unitario) + ($quantidade * $custoUnitario)) / $novaQuantidade;
                DB::table('stock_lotes')
                    ->where('id', $stockLote->id)
                    ->update([
                        'quantidade' => $novaQuantidade,
                        'custo_unitario' => $custoMedio,
                        'data_validade' => $dataValidade ?? $stockLote->data_validade,
                        'data_fabricacao' => $dataFabricacao ?? $stockLote->data_fabricacao,
                    ]);
            } else {
                DB::table('stock_lotes')->insert([
                    'produto_id' => $produtoId,
                    'unidade_id' => $unidadeId,
                    'codigo_lote' => $numeroLote,
                    'quantidade' => $quantidade,
                    'custo_unitario' => $custoUnitario,
                    'data_fabricacao' => $dataFabricacao,
                    'data_validade' => $dataValidade,
                ]);
            }

            // 7. Atualizar qtd_atual do lote
            $quantidadeTotalLote = DB::table('stock_lotes')
                ->where('codigo_lote', $numeroLote)
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->sum('quantidade');

            DB::table('lotes')
                ->where('id', $loteId)
                ->update(['qtd_atual' => $quantidadeTotalLote]);

            // 8. Criar movimentação
            $movimentacaoData = [
                'produto_id' => $produtoId,
                'lote_id' => $loteId,
                'usuario_id' => $usuarioId,
                'tipo' => 'ENTRADA',
                'qtd' => $quantidade,
                'unidade' => $unidadeBase,
                'custo_unitario' => $custoUnitario,
                'data_mov' => $dataMovimento,
                'motivo' => $motivo,
                'observacao' => $observacao ?? "Lote: {$numeroLote}",
                'de_unidade_id' => $unidadeId,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('movimentacoes', 'origem')) {
                $movimentacaoData['origem'] = $origem;
            }

            $movimentacaoId = DB::table('movimentacoes')->insertGetId($movimentacaoData);

            $loteAtualizado = DB::table('lotes')->where('id', $loteId)->first();
            $movimentacao = DB::table('movimentacoes')->where('id', $movimentacaoId)->first();

            return [
                'lote' => $loteAtualizado,
                'movimentacao' => $movimentacao,
                'codigo_lote' => $numeroLote,
                'lote_id' => $loteId,
                'movimentacao_id' => $movimentacaoId,
            ];
        };

        return $useTransaction ? DB::transaction($executar) : $executar();
    }

    private function normalizarUnidadeBase(?string $base): string
    {
        $unidadeBase = strtoupper(trim($base ?? 'UND'));
        $validas = ['UND', 'G', 'KG', 'ML', 'L', 'PCT', 'CX'];
        return in_array($unidadeBase, $validas) ? $unidadeBase : 'UND';
    }
}
