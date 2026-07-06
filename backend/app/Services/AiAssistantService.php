<?php

namespace App\Services;

use App\Models\AiAssistantLog;
use App\Support\OpenClaw\OpenClawSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lógica da API OpenClaw — consultas e ações controladas no estoque.
 */
class AiAssistantService
{
    public function estoqueBaixo(?int $unidadeId = null, int $limite = 20): array
    {
        if (! Schema::hasTable('produtos')) {
            return ['total' => 0, 'produtos' => []];
        }

        $produtosComEntrada = DB::table('movimentacoes')
            ->where('tipo', 'ENTRADA')
            ->distinct()
            ->pluck('produto_id');

        $q = DB::table('produtos')
            ->leftJoin('stock_lotes', function ($join) use ($unidadeId) {
                $join->on('produtos.id', '=', 'stock_lotes.produto_id')
                    ->where('stock_lotes.quantidade', '>', 0);
                if ($unidadeId) {
                    $join->where('stock_lotes.unidade_id', $unidadeId);
                }
            })
            ->leftJoin('unidades', 'stock_lotes.unidade_id', '=', 'unidades.id')
            ->select(
                'produtos.id',
                'produtos.nome',
                'produtos.unidade_base',
                'produtos.estoque_minimo',
                DB::raw('COALESCE(SUM(stock_lotes.quantidade), 0) as estoque_atual'),
                DB::raw('GROUP_CONCAT(DISTINCT unidades.nome SEPARATOR ", ") as unidades')
            )
            ->where('produtos.ativo', 1)
            ->where('produtos.estoque_minimo', '>', 0)
            ->whereIn('produtos.id', $produtosComEntrada)
            ->groupBy('produtos.id', 'produtos.nome', 'produtos.unidade_base', 'produtos.estoque_minimo')
            ->havingRaw('COALESCE(SUM(stock_lotes.quantidade), 0) < produtos.estoque_minimo')
            ->orderBy('produtos.nome')
            ->limit(min(50, max(1, $limite)));

        $lista = $q->get();

        return [
            'total' => $lista->count(),
            'produtos' => $lista->map(fn ($p) => [
                'id' => (int) $p->id,
                'nome' => $p->nome,
                'estoque_atual' => (float) $p->estoque_atual,
                'estoque_minimo' => (float) $p->estoque_minimo,
                'unidade_medida' => $p->unidade_base,
                'unidades' => $p->unidades,
            ])->values()->all(),
        ];
    }

    public function produtosVencendo(?int $unidadeId = null, int $dias = 7): array
    {
        if (! Schema::hasTable('lotes')) {
            return ['total' => 0, 'lotes' => []];
        }

        $dias = min(90, max(1, $dias));
        $limite = now()->addDays($dias)->format('Y-m-d');
        $hoje = now()->format('Y-m-d');

        $q = DB::table('lotes as l')
            ->leftJoin('produtos as p', 'l.produto_id', '=', 'p.id')
            ->leftJoin('unidades as u', 'l.unidade_id', '=', 'u.id')
            ->whereNotNull('l.data_validade')
            ->where('l.data_validade', '<=', $limite)
            ->where('l.data_validade', '>=', $hoje)
            ->where('l.ativo', 1)
            ->where('l.qtd_atual', '>', 0)
            ->select(
                'l.id',
                'p.nome as produto',
                'l.numero_lote',
                'l.data_validade',
                'l.qtd_atual as quantidade',
                'u.id as unidade_id',
                'u.nome as unidade'
            )
            ->orderBy('l.data_validade')
            ->limit(30);

        if ($unidadeId) {
            $q->where('l.unidade_id', $unidadeId);
        }

        $lotes = $q->get();

        return [
            'total' => $lotes->count(),
            'dias' => $dias,
            'lotes' => $lotes->map(fn ($l) => [
                'id' => (int) $l->id,
                'produto' => $l->produto,
                'lote' => $l->numero_lote,
                'validade' => $l->data_validade,
                'quantidade' => (float) $l->quantidade,
                'unidade_id' => (int) $l->unidade_id,
                'unidade' => $l->unidade,
            ])->values()->all(),
        ];
    }

    /** @param  array<string, mixed>  $params */
    public function produto(array $params): array
    {
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $nome = trim((string) ($params['nome'] ?? $params['q'] ?? ''));

        if (! $id && $nome === '') {
            return ['erro' => 'Informe id ou nome do produto.'];
        }

        $q = DB::table('produtos')->where('ativo', 1);
        if ($id) {
            $q->where('id', $id);
        } else {
            $q->where('nome', 'like', '%'.$nome.'%');
        }

        $produtos = $q->limit(10)->get(['id', 'nome', 'unidade_base', 'estoque_minimo', 'categoria']);

        if ($produtos->isEmpty()) {
            return ['total' => 0, 'produtos' => [], 'mensagem' => 'Nenhum produto encontrado.'];
        }

        $unidadeId = isset($params['unidade_id']) ? (int) $params['unidade_id'] : null;
        $resultado = [];

        foreach ($produtos as $p) {
            $estoqueQ = DB::table('stock_lotes')
                ->join('unidades', 'stock_lotes.unidade_id', '=', 'unidades.id')
                ->where('stock_lotes.produto_id', $p->id)
                ->where('stock_lotes.quantidade', '>', 0)
                ->select('unidades.nome', 'stock_lotes.quantidade', 'stock_lotes.unidade_id');
            if ($unidadeId) {
                $estoqueQ->where('stock_lotes.unidade_id', $unidadeId);
            }
            $porUnidade = $estoqueQ->get();
            $estoqueTotal = (float) $porUnidade->sum('quantidade');

            $resultado[] = [
                'id' => (int) $p->id,
                'nome' => $p->nome,
                'categoria' => $p->categoria,
                'estoque_minimo' => (float) ($p->estoque_minimo ?? 0),
                'estoque_atual' => $estoqueTotal,
                'unidade_base' => $p->unidade_base,
                'estoque_por_unidade' => $porUnidade->map(fn ($r) => [
                    'unidade_id' => (int) $r->unidade_id,
                    'unidade' => $r->nome,
                    'quantidade' => (float) $r->quantidade,
                ])->values()->all(),
            ];
        }

        return ['total' => count($resultado), 'produtos' => $resultado];
    }

    public function relatorioUnidade(int $unidadeId): array
    {
        $unidade = DB::table('unidades')->where('id', $unidadeId)->first();
        if (! $unidade) {
            return ['erro' => 'Unidade não encontrada.'];
        }

        $qtdProdutos = (int) DB::table('stock_lotes')
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0)
            ->distinct('produto_id')
            ->count('produto_id');

        $estoqueTotal = (float) DB::table('stock_lotes')
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0)
            ->sum('quantidade');

        $abaixo = $this->estoqueBaixo($unidadeId, 10);
        $vencendo = $this->produtosVencendo($unidadeId, 15);

        $movRecentes = [];
        if (Schema::hasTable('movimentacoes')) {
            $movRecentes = DB::table('movimentacoes as m')
                ->leftJoin('produtos as p', 'm.produto_id', '=', 'p.id')
                ->where('m.de_unidade_id', $unidadeId)
                ->orderByDesc('m.data_mov')
                ->limit(5)
                ->get(['m.tipo', 'm.motivo', 'm.qtd', 'p.nome as produto', 'm.data_mov'])
                ->map(fn ($m) => [
                    'tipo' => $m->tipo,
                    'motivo' => $m->motivo,
                    'produto' => $m->produto,
                    'quantidade' => (float) $m->qtd,
                    'data' => $m->data_mov,
                ])->all();
        }

        return [
            'unidade_id' => $unidadeId,
            'unidade_nome' => $unidade->nome,
            'produtos_com_estoque' => $qtdProdutos,
            'quantidade_total_estoque' => $estoqueTotal,
            'produtos_abaixo_minimo' => $abaixo['total'],
            'lotes_vencendo' => $vencendo['total'],
            'destaques_abaixo_minimo' => array_slice($abaixo['produtos'], 0, 5),
            'destaques_vencendo' => array_slice($vencendo['lotes'], 0, 5),
            'movimentacoes_recentes' => $movRecentes,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{executado: bool, preview?: array<string, mixed>, resultado?: array<string, mixed>, mensagem: string}
     */
    public function lancarPerda(array $params): array
    {
        $produtoId = (int) ($params['produto_id'] ?? 0);
        $unidadeId = (int) ($params['unidade_id'] ?? $params['de_unidade_id'] ?? 0);
        $qtd = (float) ($params['qtd'] ?? $params['quantidade'] ?? 0);
        $confirmado = filter_var($params['confirmacao'] ?? $params['confirmado'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $observacao = trim((string) ($params['observacao'] ?? ''));

        if ($produtoId <= 0 || $unidadeId <= 0 || $qtd <= 0) {
            return [
                'executado' => false,
                'mensagem' => 'Informe produto_id, unidade_id e quantidade (qtd) válidos.',
            ];
        }

        $produto = DB::table('produtos')->where('id', $produtoId)->where('ativo', 1)->first();
        if (! $produto) {
            return ['executado' => false, 'mensagem' => 'Produto não encontrado ou inativo.'];
        }

        $unidade = DB::table('unidades')->where('id', $unidadeId)->first();
        if (! $unidade) {
            return ['executado' => false, 'mensagem' => 'Unidade não encontrada.'];
        }

        $disponivel = (float) DB::table('stock_lotes')
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0)
            ->sum('quantidade');

        $preview = [
            'acao' => 'lancar_perda',
            'produto_id' => $produtoId,
            'produto' => $produto->nome,
            'unidade_id' => $unidadeId,
            'unidade' => $unidade->nome,
            'quantidade' => $qtd,
            'estoque_disponivel' => $disponivel,
            'observacao' => $observacao ?: null,
        ];

        if (! $confirmado) {
            return [
                'executado' => false,
                'preview' => $preview,
                'mensagem' => "Confirme a perda de {$qtd} {$produto->unidade_base} de \"{$produto->nome}\" na unidade {$unidade->nome}. Reenvie com confirmacao: true.",
            ];
        }

        if ($disponivel < $qtd) {
            return [
                'executado' => false,
                'preview' => $preview,
                'mensagem' => "Estoque insuficiente. Disponível: {$disponivel}, solicitado: {$qtd}.",
            ];
        }

        $usuarioId = $this->usuarioSistemaId();
        $movId = $this->registrarSaidaPerda($produtoId, $unidadeId, $qtd, $usuarioId, $observacao);

        return [
            'executado' => true,
            'resultado' => [
                'movimentacao_id' => $movId,
                'produto' => $produto->nome,
                'unidade' => $unidade->nome,
                'quantidade' => $qtd,
            ],
            'mensagem' => "Perda registrada: {$qtd} {$produto->unidade_base} de {$produto->nome} em {$unidade->nome}.",
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{executado: bool, preview?: array<string, mixed>, resultado?: array<string, mixed>, mensagem: string}
     */
    public function cadastrarCompra(array $params): array
    {
        $nome = trim((string) ($params['nome'] ?? ''));
        $unidadeId = isset($params['unidade_id']) ? (int) $params['unidade_id'] : null;
        $itens = $params['itens'] ?? [];
        $confirmado = filter_var($params['confirmacao'] ?? $params['confirmado'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($nome === '') {
            $nome = 'Compra OpenClaw '.now()->format('d/m/Y H:i');
        }

        if ($unidadeId) {
            $unidade = DB::table('unidades')->where('id', $unidadeId)->first();
            if (! $unidade) {
                return ['executado' => false, 'mensagem' => 'Unidade não encontrada.'];
            }
        }

        $itensNormalizados = [];
        if (is_array($itens)) {
            foreach ($itens as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $pid = (int) ($item['produto_id'] ?? 0);
                $qtd = (float) ($item['quantidade_planejada'] ?? $item['quantidade'] ?? 0);
                if ($pid <= 0 || $qtd <= 0) {
                    continue;
                }
                $prod = DB::table('produtos')->where('id', $pid)->first();
                if (! $prod) {
                    continue;
                }
                $itensNormalizados[] = [
                    'produto_id' => $pid,
                    'produto' => $prod->nome,
                    'quantidade_planejada' => $qtd,
                    'unidade' => $item['unidade'] ?? $prod->unidade_base ?? 'UND',
                ];
            }
        }

        $preview = [
            'acao' => 'cadastrar_compra',
            'nome' => $nome,
            'unidade_id' => $unidadeId,
            'itens' => $itensNormalizados,
        ];

        if (! $confirmado) {
            $qtdItens = count($itensNormalizados);

            return [
                'executado' => false,
                'preview' => $preview,
                'mensagem' => "Confirme o cadastro da lista \"{$nome}\" com {$qtdItens} item(ns). Reenvie com confirmacao: true.",
            ];
        }

        $listaId = DB::table('listas_compras')->insertGetId([
            'nome' => $nome,
            'unidade_id' => $unidadeId,
            'responsavel_id' => $this->usuarioSistemaId(),
            'status' => 'RASCUNHO',
            'observacoes' => 'Criada via OpenClaw',
            'criado_em' => now(),
        ]);

        $itensCriados = 0;
        foreach ($itensNormalizados as $item) {
            DB::table('listas_itens')->insert([
                'lista_id' => $listaId,
                'produto_id' => $item['produto_id'],
                'quantidade_planejada' => $item['quantidade_planejada'],
                'quantidade_comprada' => 0,
                'valor_unitario' => 0,
                'valor_planejado' => 0,
                'valor_total' => 0,
                'unidade' => mb_substr((string) $item['unidade'], 0, 20),
            ]);
            $itensCriados++;
        }

        return [
            'executado' => true,
            'resultado' => [
                'lista_id' => $listaId,
                'nome' => $nome,
                'itens_cadastrados' => $itensCriados,
            ],
            'mensagem' => "Lista de compra #{$listaId} \"{$nome}\" criada com {$itensCriados} item(ns).",
        ];
    }

    public function registrarLog(
        string $acao,
        ?string $comando,
        array $payload,
        array $resposta,
        string $status = 'ok',
        ?int $userId = null
    ): AiAssistantLog {
        if (! Schema::hasTable('ai_assistant_logs')) {
            return new AiAssistantLog;
        }

        return AiAssistantLog::create([
            'user_id' => $userId,
            'origem' => 'openclaw',
            'comando' => $comando,
            'acao' => $acao,
            'payload' => $payload,
            'resposta' => $resposta,
            'status' => $status,
        ]);
    }

    public function validarAcao(string $acao): ?string
    {
        $acao = strtolower(trim($acao));
        foreach (OpenClawSettings::ACOES_BLOQUEADAS as $bloqueada) {
            if (str_contains($acao, $bloqueada)) {
                return 'Ação bloqueada nesta fase: '.$bloqueada.'.';
            }
        }
        if (! OpenClawSettings::acaoPermitida($acao)) {
            return 'Ação não permitida nas configurações da integração.';
        }

        return null;
    }

    public function validarUnidade(?int $unidadeId): ?string
    {
        if ($unidadeId === null || $unidadeId <= 0) {
            return null;
        }
        if (! OpenClawSettings::unidadePermitida($unidadeId)) {
            return 'Unidade não permitida nas configurações da integração.';
        }

        return null;
    }

    private function usuarioSistemaId(): int
    {
        $admin = DB::table('usuarios')
            ->where('ativo', 1)
            ->whereRaw('UPPER(TRIM(perfil)) = ?', ['ADMIN'])
            ->orderBy('id')
            ->value('id');

        return (int) ($admin ?: 1);
    }

    private function registrarSaidaPerda(int $produtoId, int $unidadeId, float $qtd, int $usuarioId, string $obs = ''): int
    {
        DB::beginTransaction();
        try {
            $lotes = DB::table('stock_lotes')
                ->leftJoin('lotes', function ($join) use ($produtoId, $unidadeId) {
                    $join->on('lotes.numero_lote', '=', 'stock_lotes.codigo_lote')
                        ->where('lotes.produto_id', '=', $produtoId)
                        ->where('lotes.unidade_id', '=', $unidadeId);
                })
                ->where('stock_lotes.produto_id', $produtoId)
                ->where('stock_lotes.unidade_id', $unidadeId)
                ->where('stock_lotes.quantidade', '>', 0)
                ->select('stock_lotes.id as stock_id', 'stock_lotes.quantidade', 'stock_lotes.codigo_lote', 'lotes.id as lote_id', 'lotes.data_validade')
                ->orderBy('lotes.data_validade')
                ->orderBy('stock_lotes.id')
                ->get();

            $restante = $qtd;
            foreach ($lotes as $lote) {
                if ($restante <= 0) {
                    break;
                }
                $usar = min($restante, (float) $lote->quantidade);
                $restante -= $usar;
                $novaQtd = (float) $lote->quantidade - $usar;

                if ($novaQtd <= 0) {
                    DB::table('stock_lotes')->where('id', $lote->stock_id)->delete();
                } else {
                    DB::table('stock_lotes')->where('id', $lote->stock_id)->update(['quantidade' => $novaQtd]);
                }

                if ($lote->lote_id) {
                    $totalLote = (float) DB::table('stock_lotes')
                        ->where('codigo_lote', $lote->codigo_lote)
                        ->where('produto_id', $produtoId)
                        ->sum('quantidade');
                    DB::table('lotes')->where('id', $lote->lote_id)->update(['qtd_atual' => $totalLote]);
                }
            }

            $nota = $obs !== '' ? 'OpenClaw: '.$obs : 'Registrado via OpenClaw';
            $produto = DB::table('produtos')->where('id', $produtoId)->first();
            $unidadeBase = strtoupper(trim((string) ($produto->unidade_base ?? 'UND')));

            $movId = DB::table('movimentacoes')->insertGetId([
                'produto_id' => $produtoId,
                'de_unidade_id' => $unidadeId,
                'para_unidade_id' => null,
                'tipo' => 'SAIDA',
                'motivo' => 'PERDA',
                'qtd' => $qtd,
                'unidade' => $unidadeBase,
                'usuario_id' => $usuarioId,
                'data_mov' => now(),
                'observacao' => $nota,
            ]);

            DB::commit();

            return (int) $movId;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
