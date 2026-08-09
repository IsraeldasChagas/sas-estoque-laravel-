<?php

namespace App\Support;

use App\Services\EntradaEstoqueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProducaoFiscalSupport
{
    public const STATUS = ['rascunho', 'planejada', 'em_producao', 'finalizada', 'cancelada'];

    public static function moduloAtivo(): bool
    {
        return Schema::hasTable('producoes') && Schema::hasTable('producao_insumos');
    }

    /** @return list<array{produto_insumo_id: int, quantidade_padrao: float, unidade_medida: ?string, nome?: string}> */
    public static function itensFicha(object $ficha, bool $preferirTabela = true): array
    {
        $itens = [];
        if ($preferirTabela && Schema::hasTable('ficha_tecnica_itens')) {
            $rows = DB::table('ficha_tecnica_itens')->where('ficha_tecnica_id', $ficha->id)->get();
            foreach ($rows as $r) {
                $itens[] = [
                    'produto_insumo_id' => (int) $r->produto_insumo_id,
                    'quantidade_padrao' => (float) $r->quantidade_padrao,
                    'unidade_medida' => $r->unidade_medida,
                ];
            }
        }
        if ($itens !== []) {
            return $itens;
        }

        $json = json_decode($ficha->ingredientes_json ?? '[]', true);
        if (! is_array($json)) {
            return [];
        }
        foreach ($json as $ing) {
            if (! is_array($ing)) {
                continue;
            }
            $pid = isset($ing['produto_id']) ? (int) $ing['produto_id'] : 0;
            if ($pid <= 0 && isset($ing['id']) && is_numeric($ing['id'])) {
                $candidato = (int) $ing['id'];
                if (DB::table('produtos')->where('id', $candidato)->exists()) {
                    $pid = $candidato;
                }
            }
            if ($pid <= 0 && ! empty($ing['nome'])) {
                $pid = (int) (DB::table('produtos')->where('nome', $ing['nome'])->value('id') ?? 0);
            }
            if ($pid <= 0) {
                continue;
            }
            $q = (float) ($ing['quantidade'] ?? 0);
            if ($q <= 0) {
                continue;
            }
            $itens[] = [
                'produto_insumo_id' => $pid,
                'quantidade_padrao' => $q,
                'unidade_medida' => $ing['unidade_medida'] ?? null,
                'nome' => $ing['nome'] ?? null,
            ];
        }

        return $itens;
    }

    public static function rendimentoFicha(object $ficha): float
    {
        $r = isset($ficha->rendimento_quantidade) ? (float) $ficha->rendimento_quantidade : 0;

        return $r > 0 ? $r : 1.0;
    }

    /** @return list<array{produto_id: int, quantidade_prevista: float, produto_nome: string}> */
    public static function calcularNecessidade(object $ficha, float $quantidadeProduzir): array
    {
        $rend = self::rendimentoFicha($ficha);
        $fator = $quantidadeProduzir / $rend;
        $out = [];
        foreach (self::itensFicha($ficha) as $it) {
            $prod = DB::table('produtos')->where('id', $it['produto_insumo_id'])->first();
            $out[] = [
                'produto_id' => $it['produto_insumo_id'],
                'quantidade_prevista' => round($it['quantidade_padrao'] * $fator, 4),
                'produto_nome' => $prod->nome ?? ('#' . $it['produto_insumo_id']),
            ];
        }

        return $out;
    }

    public static function validarEmpresaUnidadeProducao(?int $empresaId, int $unidadeId): ?string
    {
        return FiscalCompraEntradaSupport::validarEmpresaUnidade($empresaId, $unidadeId);
    }

    public static function validarInsumoMesmaEmpresa(?int $empresaId, int $unidadeId, int $produtoId): ?string
    {
        if (! $empresaId) {
            return null;
        }
        $empUnidade = FiscalMovimentacaoSupport::resolverEmpresaUnidade($unidadeId);
        if ($empUnidade && (int) $empUnidade !== (int) $empresaId) {
            return 'Unidade não pertence à empresa da produção.';
        }

        return null;
    }

    public static function alertaProdutoFinal(object $produto): ?string
    {
        $tipo = strtolower(trim((string) ($produto->tipo_fiscal ?? '')));
        if ($tipo === 'revenda' || $tipo === 'mercadoria_revenda') {
            return 'Produto marcado como revenda; confirme se deve ser produzido neste CNPJ.';
        }

        return null;
    }

    /** @param array<int, float> $quantidadesReais produto_id => qtd real */
    public static function simular(int $producaoId, int $unidadeId, ?int $empresaId, array $quantidadesReais = []): array
    {
        $producao = DB::table('producoes')->where('id', $producaoId)->first();
        if (! $producao) {
            throw new \RuntimeException('Produção não encontrada.');
        }
        $insumos = DB::table('producao_insumos')->where('producao_id', $producaoId)->get();
        $linhas = [];
        $faltas = [];
        foreach ($insumos as $ins) {
            $real = $quantidadesReais[(int) $ins->produto_id] ?? (float) ($ins->quantidade_real ?? $ins->quantidade_prevista);
            $disp = ProducaoEstoqueSupport::saldoDisponivel((int) $ins->produto_id, $unidadeId);
            $prod = DB::table('produtos')->where('id', $ins->produto_id)->first();
            $linhas[] = [
                'produto_id' => (int) $ins->produto_id,
                'produto_nome' => $prod->nome ?? '',
                'quantidade_prevista' => (float) $ins->quantidade_prevista,
                'quantidade_real' => $real,
                'disponivel' => $disp,
                'faltante' => max(0, $real - $disp),
            ];
            if ($real > $disp + 0.0001) {
                $faltas[] = $prod->nome ?? (string) $ins->produto_id;
            }
        }

        return [
            'pode_finalizar' => $faltas === [],
            'faltas' => $faltas,
            'insumos' => $linhas,
        ];
    }

    public static function sincronizarItensFicha(int $fichaId): void
    {
        if (! Schema::hasTable('ficha_tecnica_itens')) {
            return;
        }
        $ficha = DB::table('fichas_tecnicas')->where('id', $fichaId)->first();
        if (! $ficha) {
            return;
        }
        DB::table('ficha_tecnica_itens')->where('ficha_tecnica_id', $fichaId)->delete();
        foreach (self::itensFicha($ficha, false) as $it) {
            DB::table('ficha_tecnica_itens')->insert([
                'ficha_tecnica_id' => $fichaId,
                'produto_insumo_id' => $it['produto_insumo_id'],
                'quantidade_padrao' => $it['quantidade_padrao'],
                'unidade_medida' => $it['unidade_medida'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param array<int, float>|null $quantidadesReais
     * @return array<string, mixed>
     */
    public static function finalizar(int $producaoId, int $usuarioId, ?array $quantidadesReais = null, float $custoAdicional = 0): array
    {
        if (! self::moduloAtivo()) {
            throw new \RuntimeException('Módulo de produção não disponível.');
        }

        return DB::transaction(function () use ($producaoId, $usuarioId, $quantidadesReais, $custoAdicional) {
            $producao = DB::table('producoes')->where('id', $producaoId)->lockForUpdate()->first();
            if (! $producao) {
                throw new \RuntimeException('Produção não encontrada.');
            }
            if ($producao->status === 'finalizada') {
                throw new \RuntimeException('Produção já finalizada.');
            }
            if ($producao->status === 'cancelada') {
                throw new \RuntimeException('Produção cancelada.');
            }

            $unidadeId = (int) $producao->unidade_id;
            $empresaId = isset($producao->empresa_id) ? (int) $producao->empresa_id : FiscalMovimentacaoSupport::resolverEmpresaUnidade($unidadeId);
            $qtdProduzida = (float) ($producao->quantidade_produzida ?? $producao->quantidade_planejada);
            if ($qtdProduzida <= 0) {
                throw new \RuntimeException('Quantidade produzida inválida.');
            }

            $sim = self::simular($producaoId, $unidadeId, $empresaId, $quantidadesReais ?? []);
            if (! $sim['pode_finalizar']) {
                throw new \RuntimeException('Saldo insuficiente: ' . implode(', ', $sim['faltas']));
            }

            require_once dirname(__DIR__, 2) . '/routes/saida_unidade_helpers.php';

            $insumos = DB::table('producao_insumos')->where('producao_id', $producaoId)->get();
            $custoInsumos = 0.0;

            foreach ($insumos as $ins) {
                $produtoId = (int) $ins->produto_id;
                $real = $quantidadesReais[$produtoId] ?? (float) ($ins->quantidade_real ?? $ins->quantidade_prevista);
                if ($real <= 0) {
                    continue;
                }

                $errEmp = self::validarInsumoMesmaEmpresa($empresaId, $unidadeId, $produtoId);
                if ($errEmp) {
                    throw new \RuntimeException($errEmp);
                }

                $baixa = ProducaoEstoqueSupport::baixarFifo($produtoId, $unidadeId, $real, null, false);
                $produto = DB::table('produtos')->where('id', $produtoId)->first();
                $unidadeBase = unidadeGravacaoMovimentacao(normalizarUnidadeMedidaSaida($produto->unidade_base ?? 'UND'));

                $movData = array_merge([
                    'produto_id' => $produtoId,
                    'lote_id' => $baixa['lote_id'],
                    'usuario_id' => $usuarioId,
                    'tipo' => 'SAIDA',
                    'qtd' => $real,
                    'unidade' => $unidadeBase,
                    'custo_unitario' => $baixa['custo_medio'],
                    'data_mov' => now(),
                    'motivo' => 'PRODUCAO',
                    'observacao' => 'Produção #' . $producaoId . ' — insumo',
                    'de_unidade_id' => $unidadeId,
                    'producao_id' => $producaoId,
                ], FiscalMovimentacaoSupport::buildCamposMovimentacao(
                    ['motivo' => 'PRODUCAO', 'motivo_detalhe' => 'Consumo produção #' . $producaoId],
                    false,
                    $unidadeId,
                    null,
                    (float) $baixa['custo_medio'],
                    $real
                ));

                if (Schema::hasColumn('movimentacoes', 'producao_id')) {
                    $movData['producao_id'] = $producaoId;
                }

                $movId = DB::table('movimentacoes')->insertGetId($movData);
                $movRow = DB::table('movimentacoes')->where('id', $movId)->first();
                if ($movRow) {
                    FiscalMovimentacaoSupport::posRegistrarSaida($movId, $movRow);
                }

                foreach ($baixa['lotes_usados'] as $lu) {
                    DB::table('producao_lotes')->insert([
                        'producao_id' => $producaoId,
                        'producao_insumo_id' => $ins->id,
                        'lote_id' => $lu['lote_id'],
                        'codigo_lote' => $lu['codigo_lote'],
                        'quantidade_consumida' => $lu['quantidade'],
                        'custo_unitario' => $lu['custo_unitario'],
                        'custo_total' => $lu['quantidade'] * $lu['custo_unitario'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $custoLinha = (float) $baixa['custo_total'];
                $custoInsumos += $custoLinha;
                DB::table('producao_insumos')->where('id', $ins->id)->update([
                    'quantidade_real' => $real,
                    'custo_total' => $custoLinha,
                    'movimentacao_id' => $movId,
                    'updated_at' => now(),
                ]);
            }

            $custoTotal = $custoInsumos + max(0, $custoAdicional);
            $custoUnitario = $qtdProduzida > 0 ? $custoTotal / $qtdProduzida : 0;

            $numeroLote = 'PROD-' . $producaoId . '-' . now()->format('YmdHis');
            /** @var EntradaEstoqueService $entradaSvc */
            $entradaSvc = app(EntradaEstoqueService::class);
            $entrada = $entradaSvc->registrarEntrada([
                'produto_id' => (int) $producao->produto_final_id,
                'unidade_id' => $unidadeId,
                'quantidade' => $qtdProduzida,
                'custo_unitario' => $custoUnitario,
                'usuario_id' => $usuarioId,
                'numero_lote' => $numeroLote,
                'motivo' => 'PRODUCAO',
                'observacao' => 'Entrada produção #' . $producaoId,
                'origem' => 'PRODUCAO',
            ], false);

            $movEntradaId = (int) ($entrada['movimentacao_id'] ?? 0);
            $loteFinalId = (int) ($entrada['lote_id'] ?? 0);

            $movUp = [];
            foreach ([
                'tipo_entrada_fiscal' => 'entrada_producao',
                'empresa_id' => $empresaId,
                'producao_id' => $producaoId,
                'custo_total' => $custoTotal,
                'tipo_movimentacao' => 'producao',
            ] as $col => $val) {
                if (Schema::hasColumn('movimentacoes', $col)) {
                    $movUp[$col] = $val;
                }
            }
            if ($movUp !== []) {
                DB::table('movimentacoes')->where('id', $movEntradaId)->update($movUp);
            }

            if ($loteFinalId && Schema::hasColumn('lotes', 'producao_id')) {
                DB::table('lotes')->where('id', $loteFinalId)->update([
                    'producao_id' => $producaoId,
                    'empresa_id' => $empresaId,
                ]);
            }

            self::criarEventoProducao($producaoId, $empresaId, $unidadeId, (int) $producao->produto_final_id, $custoTotal);

            DB::table('producoes')->where('id', $producaoId)->update([
                'status' => 'finalizada',
                'data_producao' => now(),
                'custo_insumos' => $custoInsumos,
                'custo_adicional' => $custoAdicional,
                'custo_total' => $custoTotal,
                'custo_unitario' => $custoUnitario,
                'quantidade_produzida' => $qtdProduzida,
                'lote_final_id' => $loteFinalId ?: null,
                'movimentacao_entrada_id' => $movEntradaId ?: null,
                'updated_at' => now(),
            ]);

            $fichaId = Schema::hasColumn('producoes', 'ficha_tecnica_id')
                ? (int) ($producao->ficha_tecnica_id ?? 0)
                : 0;
            $produtoFinalId = (int) ($producao->produto_final_id ?? 0);
            $cardapioEntradas = CardapioEstoqueSupport::entrarDaProducao(
                $unidadeId,
                $qtdProduzida,
                $producaoId,
                $fichaId > 0 ? $fichaId : null,
                $produtoFinalId > 0 ? $produtoFinalId : null,
                $usuarioId
            );

            return [
                'producao_id' => $producaoId,
                'custo_total' => $custoTotal,
                'custo_unitario' => $custoUnitario,
                'lote_final_id' => $loteFinalId,
                'movimentacao_entrada_id' => $movEntradaId,
                'cardapio_entradas' => $cardapioEntradas,
            ];
        });
    }

    public static function criarEventoProducao(int $producaoId, ?int $empresaId, int $unidadeId, int $produtoFinalId, float $valorBase): void
    {
        if (! Schema::hasTable('eventos_fiscais')) {
            return;
        }
        if (DB::table('eventos_fiscais')->where('producao_id', $producaoId)->whereNotIn('status', ['cancelado'])->exists()) {
            return;
        }
        DB::table('eventos_fiscais')->insert([
            'empresa_id' => $empresaId,
            'unidade_id' => $unidadeId,
            'producao_id' => $producaoId,
            'movimentacao_id' => null,
            'produto_id' => $produtoFinalId,
            'lote_id' => null,
            'tipo_evento' => 'producao',
            'origem_evento' => 'producao_estoque',
            'status' => 'sem_impacto',
            'data_evento' => now(),
            'valor_base' => round($valorBase, 4),
            'valor_estimado' => null,
            'observacao' => 'Produção #' . $producaoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function criarProducao(array $data, int $usuarioId): int
    {
        $fichaId = (int) ($data['ficha_tecnica_id'] ?? 0);
        $ficha = $fichaId ? DB::table('fichas_tecnicas')->where('id', $fichaId)->first() : null;
        if (! $ficha) {
            throw new \RuntimeException('Ficha técnica não encontrada.');
        }

        $unidadeId = (int) $data['unidade_id'];
        $empresaId = isset($data['empresa_id']) ? (int) $data['empresa_id'] : FiscalMovimentacaoSupport::resolverEmpresaUnidade($unidadeId);
        $err = self::validarEmpresaUnidadeProducao($empresaId, $unidadeId);
        if ($err) {
            throw new \RuntimeException($err);
        }

        $produtoFinalId = (int) ($data['produto_final_id'] ?? $ficha->produto_final_id ?? 0);
        if ($produtoFinalId <= 0) {
            throw new \RuntimeException('Informe o produto final da produção.');
        }
        $produto = DB::table('produtos')->where('id', $produtoFinalId)->first();
        if (! $produto) {
            throw new \RuntimeException('Produto final não encontrado.');
        }

        $qtdPlan = (float) ($data['quantidade_planejada'] ?? 0);
        if ($qtdPlan <= 0) {
            throw new \RuntimeException('Quantidade planejada inválida.');
        }

        self::sincronizarItensFicha($fichaId);
        $itens = self::itensFicha($ficha);
        if ($itens === []) {
            throw new \RuntimeException('Ficha sem insumos vinculados a produtos de estoque.');
        }

        $versao = (int) ($ficha->versao ?? 1);
        $id = DB::table('producoes')->insertGetId([
            'empresa_id' => $empresaId,
            'unidade_id' => $unidadeId,
            'ficha_tecnica_id' => $fichaId,
            'ficha_versao' => $versao,
            'produto_final_id' => $produtoFinalId,
            'quantidade_planejada' => $qtdPlan,
            'quantidade_produzida' => $data['quantidade_produzida'] ?? $qtdPlan,
            'status' => 'planejada',
            'usuario_id' => $usuarioId,
            'observacao' => $data['observacao'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::calcularNecessidade($ficha, $qtdPlan) as $n) {
            DB::table('producao_insumos')->insert([
                'producao_id' => $id,
                'produto_id' => $n['produto_id'],
                'quantidade_prevista' => $n['quantidade_prevista'],
                'quantidade_real' => $n['quantidade_prevista'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $id;
    }
}
