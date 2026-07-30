<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class VendaFiscalSupport
{
    public const STATUS_VENDA = ['rascunho', 'finalizada', 'cancelada'];

    public const STATUS_DOCUMENTO = ['pendente', 'processando', 'autorizado', 'rejeitado', 'cancelado', 'contingencia'];

    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('vendas') && Schema::hasTable('venda_itens');
    }

    public static function resolverEmpresaUnidade(int $unidadeId): ?int
    {
        return FiscalMovimentacaoSupport::resolverEmpresaUnidade($unidadeId);
    }

    public static function resolverEmpresaLote(?int $loteId): ?int
    {
        if (! $loteId || ! Schema::hasTable('lotes')) {
            return null;
        }
        $lote = DB::table('lotes')->where('id', $loteId)->first();
        if (! $lote) {
            return null;
        }
        if (! empty($lote->empresa_id)) {
            return (int) $lote->empresa_id;
        }

        return self::resolverEmpresaUnidade((int) ($lote->unidade_id ?? 0));
    }

    public static function resolverEmpresaEstoqueProduto(int $produtoId, int $unidadeId): ?int
    {
        if (Schema::hasTable('lotes')) {
            $lote = DB::table('lotes')
                ->where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->where('qtd_atual', '>', 0)
                ->orderByDesc('id')
                ->first(['empresa_id', 'unidade_id']);
            if ($lote && ! empty($lote->empresa_id)) {
                return (int) $lote->empresa_id;
            }
        }

        return self::resolverEmpresaUnidade($unidadeId);
    }

    /**
     * @return array{ok: bool, message?: string, empresa_estoque_id?: int}
     */
    public static function validarPropriedadeFiscal(
        ?int $empresaPdvId,
        int $unidadePdvId,
        int $produtoId,
        float $quantidade,
        ?int $loteId = null
    ): array {
        if (! $empresaPdvId) {
            $empresaPdvId = self::resolverEmpresaUnidade($unidadePdvId);
        }
        if (! $empresaPdvId) {
            return ['ok' => true];
        }

        $empresaEstoque = $loteId ? self::resolverEmpresaLote($loteId) : self::resolverEmpresaEstoqueProduto($produtoId, $unidadePdvId);
        if (! $empresaEstoque) {
            $empresaEstoque = self::resolverEmpresaUnidade($unidadePdvId);
        }

        if ($empresaEstoque && (int) $empresaEstoque !== (int) $empresaPdvId) {
            return [
                'ok' => false,
                'empresa_estoque_id' => (int) $empresaEstoque,
                'message' => self::mensagemBloqueioCnpj((int) $empresaEstoque, (int) $empresaPdvId),
            ];
        }

        $saldo = ProducaoEstoqueSupport::saldoDisponivel($produtoId, $unidadePdvId);
        if ($saldo + 0.0001 < $quantidade) {
            return [
                'ok' => false,
                'message' => "Produto sem estoque disponível para este CNPJ/unidade. Necessário: {$quantidade}, disponível: {$saldo}.",
            ];
        }

        return ['ok' => true, 'empresa_estoque_id' => $empresaEstoque];
    }

    public static function mensagemBloqueioCnpj(int $empresaEstoqueId, int $empresaPdvId): string
    {
        $cnpjE = self::cnpjEmpresa($empresaEstoqueId);
        $cnpjP = self::cnpjEmpresa($empresaPdvId);

        return "Venda não permitida. Este estoque pertence ao CNPJ {$cnpjE}. O PDV atual pertence ao CNPJ {$cnpjP}. Realize primeiro a operação de movimentação entre as empresas.";
    }

    public static function cnpjEmpresa(int $empresaId): string
    {
        if (Schema::hasTable('empresas')) {
            $doc = DB::table('empresas')->where('id', $empresaId)->value('cnpj');
        } elseif (Schema::hasTable('fiscal_empresas')) {
            $doc = DB::table('fiscal_empresas')->where('id', $empresaId)->value('cnpj');
        } else {
            $doc = null;
        }
        $n = FiscalCadastroSupport::normalizarCnpj($doc);
        if ($n && strlen($n) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $n);
        }

        return $doc ? (string) $doc : (string) $empresaId;
    }

    public static function registrarBloqueio(
        ?int $usuarioId,
        ?int $empresaPdvId,
        ?int $empresaEstoqueId,
        ?int $unidadePdvId,
        ?int $produtoId,
        ?float $quantidade,
        string $detalhe
    ): void {
        if (! Schema::hasTable('venda_fiscal_bloqueios_log')) {
            return;
        }
        DB::table('venda_fiscal_bloqueios_log')->insert([
            'usuario_id' => $usuarioId,
            'empresa_pdv_id' => $empresaPdvId,
            'empresa_estoque_id' => $empresaEstoqueId,
            'unidade_pdv_id' => $unidadePdvId,
            'produto_id' => $produtoId,
            'quantidade' => $quantidade,
            'motivo' => 'cnpj_incompativel',
            'detalhe' => $detalhe,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function snapshotProduto(object $produto): array
    {
        return FiscalCompraEntradaSupport::snapshotCadastroProduto($produto);
    }

    /** @param list<array{produto_id: int, quantidade: float, preco_unitario: float, desconto?: float}> $itens */
    public static function finalizarVenda(array $payload, int $usuarioId): array
    {
        if (! self::moduloAtivo()) {
            throw new \RuntimeException('Módulo de vendas fiscal indisponível.');
        }

        $unidadeId = (int) ($payload['unidade_id'] ?? 0);
        $empresaId = isset($payload['empresa_id']) ? (int) $payload['empresa_id'] : self::resolverEmpresaUnidade($unidadeId);
        $itens = $payload['itens'] ?? [];
        if ($unidadeId <= 0 || ! is_array($itens) || $itens === []) {
            throw new \InvalidArgumentException('Unidade e itens são obrigatórios.');
        }

        $errUni = FiscalCompraEntradaSupport::validarEmpresaUnidade($empresaId, $unidadeId);
        if ($errUni) {
            throw new \RuntimeException($errUni);
        }

        foreach ($itens as $it) {
            $pid = (int) ($it['produto_id'] ?? 0);
            $qtd = (float) ($it['quantidade'] ?? 0);
            if ($pid <= 0 || $qtd <= 0) {
                throw new \InvalidArgumentException('Item de venda inválido.');
            }
            $val = self::validarPropriedadeFiscal($empresaId, $unidadeId, $pid, $qtd, isset($it['lote_id']) ? (int) $it['lote_id'] : null);
            if (! ($val['ok'] ?? false)) {
                self::registrarBloqueio(
                    $usuarioId,
                    $empresaId,
                    $val['empresa_estoque_id'] ?? null,
                    $unidadeId,
                    $pid,
                    $qtd,
                    $val['message'] ?? 'bloqueio'
                );
                throw new \RuntimeException($val['message'] ?? 'Venda bloqueada.');
            }
        }

        return DB::transaction(function () use ($payload, $usuarioId, $unidadeId, $empresaId, $itens) {
            require_once dirname(__DIR__, 2) . '/routes/saida_unidade_helpers.php';

            $valorBruto = 0.0;
            $descontoTotal = 0.0;
            $custoTotalVenda = 0.0;

            $insertVenda = [
                'empresa_id' => $empresaId,
                'unidade_id' => $unidadeId,
                'usuario_id' => $usuarioId,
                'pdv_terminal' => $payload['pdv_terminal'] ?? null,
                'status' => 'finalizada',
                'valor_bruto' => 0,
                'desconto' => 0,
                'valor_liquido' => 0,
                'forma_pagamento' => $payload['forma_pagamento'] ?? null,
                'numero_documento' => $payload['numero_documento'] ?? null,
                'chave_acesso' => $payload['chave_acesso'] ?? null,
                'status_documento' => $payload['status_documento'] ?? 'pendente',
                'data_venda' => now(),
                'observacao' => $payload['observacao'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('vendas', 'mesa_id') && ! empty($payload['mesa_id'])) {
                $insertVenda['mesa_id'] = (int) $payload['mesa_id'];
            }
            if (Schema::hasColumn('vendas', 'comanda_id') && ! empty($payload['comanda_id'])) {
                $insertVenda['comanda_id'] = (int) $payload['comanda_id'];
            }
            if (Schema::hasColumn('vendas', 'reserva_mesa_id') && ! empty($payload['reserva_mesa_id'])) {
                $insertVenda['reserva_mesa_id'] = (int) $payload['reserva_mesa_id'];
            }
            if (Schema::hasColumn('vendas', 'origem_venda') && ! empty($payload['origem_venda'])) {
                $insertVenda['origem_venda'] = (string) $payload['origem_venda'];
            }
            $insertVenda = array_merge($insertVenda, PdvConfigSupport::extrairCamposPagamentoVenda($payload));

            $vendaId = DB::table('vendas')->insertGetId($insertVenda);

            foreach ($itens as $raw) {
                $produtoId = (int) $raw['produto_id'];
                $qtd = (float) $raw['quantidade'];
                $preco = (float) ($raw['preco_unitario'] ?? 0);
                $desc = (float) ($raw['desconto'] ?? 0);
                $valorItem = round($preco * $qtd - $desc, 2);
                $valorBruto += round($preco * $qtd, 2);
                $descontoTotal += $desc;

                $baixa = ProducaoEstoqueSupport::baixarFifo($produtoId, $unidadeId, $qtd, null, false);
                $produto = DB::table('produtos')->where('id', $produtoId)->first();
                $unidadeBase = unidadeGravacaoMovimentacao(normalizarUnidadeMedidaSaida($produto->unidade_base ?? 'UND'));

                $movData = array_merge([
                    'produto_id' => $produtoId,
                    'lote_id' => $baixa['lote_id'],
                    'usuario_id' => $usuarioId,
                    'tipo' => 'SAIDA',
                    'qtd' => $qtd,
                    'unidade' => $unidadeBase,
                    'custo_unitario' => $baixa['custo_medio'],
                    'data_mov' => now(),
                    'motivo' => 'VENDA',
                    'observacao' => 'Venda #' . $vendaId,
                    'de_unidade_id' => $unidadeId,
                ], FiscalMovimentacaoSupport::buildCamposMovimentacao(
                    ['motivo' => 'CONSUMO', 'motivo_detalhe' => 'Venda #' . $vendaId],
                    false,
                    $unidadeId,
                    null,
                    (float) $baixa['custo_medio'],
                    $qtd
                ));

                if (Schema::hasColumn('movimentacoes', 'motivo')) {
                    $movData['motivo'] = 'VENDA';
                }
                if (Schema::hasColumn('movimentacoes', 'tipo_movimentacao')) {
                    unset($movData['tipo_movimentacao']);
                }

                $movId = DB::table('movimentacoes')->insertGetId($movData);

                $custoItem = (float) $baixa['custo_total'];
                $custoTotalVenda += $custoItem;

                $itemId = DB::table('venda_itens')->insertGetId([
                    'venda_id' => $vendaId,
                    'produto_id' => $produtoId,
                    'lote_id' => $baixa['lote_id'],
                    'empresa_id' => $empresaId,
                    'unidade_id' => $unidadeId,
                    'quantidade' => $qtd,
                    'preco_unitario' => $preco,
                    'desconto' => $desc,
                    'valor_total' => $valorItem,
                    'custo_unitario' => $baixa['custo_medio'],
                    'custo_total' => $custoItem,
                    'movimentacao_id' => $movId,
                    'fiscal_snapshot' => json_encode(self::snapshotProduto($produto)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                self::registrarTributosPotenciaisItem($empresaId, $vendaId, (int) $itemId, $produto, $valorItem);
            }

            $valorLiquido = round($valorBruto - $descontoTotal, 2);
            $encargos = PdvConfigSupport::extrairEncargosVenda($valorLiquido, $payload);
            $taxaServico = (float) ($encargos['taxa_servico'] ?? 0);
            $pagamentoCantor = (float) ($encargos['pagamento_cantor'] ?? 0);
            $valorLiquido = round($valorLiquido + $taxaServico + $pagamentoCantor, 2);

            DB::table('vendas')->where('id', $vendaId)->update(array_merge([
                'valor_bruto' => $valorBruto,
                'desconto' => $descontoTotal,
                'valor_liquido' => $valorLiquido,
                'custo_total' => $custoTotalVenda,
                'updated_at' => now(),
            ], $encargos));

            self::criarEventoVenda($vendaId, $empresaId, $unidadeId, $valorLiquido);

            return [
                'venda_id' => $vendaId,
                'valor_liquido' => $valorLiquido,
                'custo_total' => $custoTotalVenda,
                'taxa_servico' => $taxaServico,
                'pagamento_cantor' => $pagamentoCantor,
            ];
        });
    }

    protected static function registrarTributosPotenciaisItem(
        ?int $empresaId,
        int $vendaId,
        int $itemId,
        object $produto,
        float $base
    ): void {
        if (! Schema::hasTable('tributos_venda')) {
            return;
        }
        foreach (['icms', 'pis', 'cofins'] as $tipo) {
            DB::table('tributos_venda')->insert([
                'empresa_id' => $empresaId,
                'venda_id' => $vendaId,
                'venda_item_id' => $itemId,
                'tipo_tributo' => $tipo,
                'base_calculo' => $base,
                'aliquota' => null,
                'valor' => 0,
                'status' => 'calculado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function criarEventoVenda(int $vendaId, ?int $empresaId, int $unidadeId, float $valorBase): void
    {
        if (! Schema::hasTable('eventos_fiscais')) {
            return;
        }
        DB::table('eventos_fiscais')->insert([
            'empresa_id' => $empresaId,
            'unidade_id' => $unidadeId,
            'venda_id' => $vendaId,
            'movimentacao_id' => null,
            'produto_id' => null,
            'lote_id' => null,
            'tipo_evento' => 'venda',
            'origem_evento' => 'pdv_venda',
            'status' => 'pendente_analise',
            'data_evento' => now(),
            'valor_base' => round($valorBase, 4),
            'observacao' => 'Venda #' . $vendaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
