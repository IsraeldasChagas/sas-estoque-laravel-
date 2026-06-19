<?php

namespace App\Services;

use App\Support\Financeiro\FinanceiroGerencialCalculo;
use App\Support\SasIa\SasIaContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferramentas seguras do SAS IA — só leitura, com escopo de unidade e permissões.
 * A IA nunca acessa SQL diretamente; só estes métodos.
 */
class SasIaToolService
{
    public function __construct(
        private SasIaDocumentService $documentService
    ) {}

    /**
     * Executa ferramenta e retorna array serializável para a OpenAI.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function executar(SasIaContext $ctx, string $toolName, array $args): array
    {
        if (! $ctx->podeUsarFerramenta($toolName)) {
            return [
                'erro' => true,
                'mensagem' => 'Não encontrei informação suficiente ou você não tem permissão para acessar esse dado.',
            ];
        }

        return match ($toolName) {
            'consultar_produtos_abaixo_estoque_minimo' => $this->produtosAbaixoMinimo($ctx),
            'consultar_produto_por_nome' => $this->produtoPorNome($ctx, $args),
            'consultar_estoque_por_unidade' => $this->estoquePorUnidade($ctx, $args),
            'consultar_movimentacoes_recentes' => $this->movimentacoesRecentes($ctx, $args),
            'consultar_vendas_do_dia' => $this->vendasDoDia($ctx, $args),
            'consultar_compras_recentes' => $this->comprasRecentes($ctx, $args),
            'consultar_fornecedores' => $this->fornecedores($ctx, $args),
            'consultar_logs_recentes' => $this->logsRecentes($ctx, $args),
            'consultar_resumo_financeiro' => $this->resumoFinanceiro($ctx, $args),
            'consultar_resumo_produtos' => $this->resumoProdutos($ctx, $args),
            'consultar_manual_documentacao' => $this->manualDocumentacao($ctx, $args),
            default => ['erro' => true, 'mensagem' => 'Ferramenta desconhecida.'],
        };
    }

    /** @return array<string, mixed> */
    private function produtosAbaixoMinimo(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('produtos')) {
            return ['total' => 0, 'produtos' => []];
        }

        $produtosComEntrada = DB::table('movimentacoes')
            ->where('tipo', 'ENTRADA')
            ->distinct()
            ->pluck('produto_id');

        $q = DB::table('produtos')
            ->leftJoin('stock_lotes', function ($join) use ($ctx) {
                $join->on('produtos.id', '=', 'stock_lotes.produto_id')
                    ->where('stock_lotes.quantidade', '>', 0);
                if ($uid = $ctx->unidadeEfetiva()) {
                    $join->where('stock_lotes.unidade_id', $uid);
                }
            })
            ->select(
                'produtos.id',
                'produtos.nome',
                'produtos.unidade_base',
                'produtos.estoque_minimo',
                DB::raw('COALESCE(SUM(stock_lotes.quantidade), 0) as estoque_atual')
            )
            ->where('produtos.ativo', 1)
            ->where('produtos.estoque_minimo', '>', 0)
            ->whereIn('produtos.id', $produtosComEntrada)
            ->groupBy('produtos.id', 'produtos.nome', 'produtos.unidade_base', 'produtos.estoque_minimo')
            ->havingRaw('COALESCE(SUM(stock_lotes.quantidade), 0) < produtos.estoque_minimo')
            ->limit(30);

        $lista = $q->get();

        return [
            'total' => $lista->count(),
            'produtos' => $lista->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'estoque_atual' => (float) $p->estoque_atual,
                'estoque_minimo' => (float) $p->estoque_minimo,
                'unidade' => $p->unidade_base,
            ])->values()->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function produtoPorNome(SasIaContext $ctx, array $args): array
    {
        $nome = trim((string) ($args['nome'] ?? ''));
        if ($nome === '') {
            return ['erro' => true, 'mensagem' => 'Informe o nome do produto.'];
        }

        $q = DB::table('produtos')
            ->where('ativo', 1)
            ->where('nome', 'like', '%'.$nome.'%')
            ->limit(15);

        $produtos = $q->get(['id', 'nome', 'unidade_base', 'estoque_minimo', 'categoria']);

        $resultado = [];
        foreach ($produtos as $p) {
            $estoqueQ = DB::table('stock_lotes')
                ->where('produto_id', $p->id)
                ->where('quantidade', '>', 0);
            if ($uid = $ctx->unidadeEfetiva()) {
                $estoqueQ->where('unidade_id', $uid);
            }
            $estoque = (float) $estoqueQ->sum('quantidade');
            $resultado[] = [
                'id' => $p->id,
                'nome' => $p->nome,
                'categoria' => $p->categoria ?? null,
                'estoque_minimo' => (float) ($p->estoque_minimo ?? 0),
                'estoque_atual' => $estoque,
                'unidade_base' => $p->unidade_base,
            ];
        }

        return ['total' => count($resultado), 'produtos' => $resultado];
    }

    /** @param  array<string, mixed>  $args */
    private function estoquePorUnidade(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('stock_lotes')) {
            return ['unidades' => []];
        }

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : null;
        if ($ctx->unidadeEfetiva()) {
            $unidadeId = $ctx->unidadeEfetiva();
        }

        $q = DB::table('stock_lotes')
            ->join('unidades', 'stock_lotes.unidade_id', '=', 'unidades.id')
            ->join('produtos', 'stock_lotes.produto_id', '=', 'produtos.id')
            ->where('stock_lotes.quantidade', '>', 0)
            ->select(
                'unidades.id as unidade_id',
                'unidades.nome as unidade_nome',
                DB::raw('COUNT(DISTINCT stock_lotes.produto_id) as qtd_produtos'),
                DB::raw('SUM(stock_lotes.quantidade) as qtd_total')
            )
            ->groupBy('unidades.id', 'unidades.nome');

        if ($unidadeId) {
            $q->where('unidades.id', $unidadeId);
        }

        return [
            'unidades' => $q->limit(20)->get()->map(fn ($r) => [
                'unidade_id' => $r->unidade_id,
                'unidade_nome' => $r->unidade_nome,
                'qtd_produtos' => (int) $r->qtd_produtos,
                'quantidade_total' => (float) $r->qtd_total,
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function movimentacoesRecentes(SasIaContext $ctx, array $args): array
    {
        $dias = min(30, max(1, (int) ($args['dias'] ?? 7)));
        $desde = now()->subDays($dias)->format('Y-m-d H:i:s');

        $q = DB::table('movimentacoes as m')
            ->leftJoin('produtos as p', 'm.produto_id', '=', 'p.id')
            ->leftJoin('unidades as u', 'm.de_unidade_id', '=', 'u.id')
            ->where('m.data_mov', '>=', $desde)
            ->select(
                'm.id',
                'm.tipo',
                'm.motivo',
                'm.qtd',
                'm.unidade',
                'm.data_mov',
                'p.nome as produto_nome',
                'u.nome as unidade_nome'
            )
            ->orderByDesc('m.data_mov')
            ->limit(25);

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where(function ($w) use ($unidadeId) {
                $w->where('m.de_unidade_id', $unidadeId)
                    ->orWhere('m.para_unidade_id', $unidadeId);
            });
        }

        return [
            'periodo_dias' => $dias,
            'movimentacoes' => $q->get()->map(fn ($m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'motivo' => $m->motivo,
                'produto' => $m->produto_nome,
                'quantidade' => (float) $m->qtd,
                'unidade_medida' => $m->unidade,
                'unidade_nome' => $m->unidade_nome,
                'data' => $m->data_mov,
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function vendasDoDia(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('fechamentos_caixa')) {
            return ['erro' => true, 'mensagem' => 'Módulo de fechamento de caixa não disponível.'];
        }

        $data = trim((string) ($args['data'] ?? ''));
        if ($data === '') {
            $data = now()->format('Y-m-d');
        }

        $q = DB::table('fechamentos_caixa as f')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->whereDate('f.data_fechamento', $data);

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where('f.unidade_id', $unidadeId);
        }

        $rows = $q->select('f.id', 'f.unidade_id', 'u.nome as unidade_nome', 'f.linhas_json', 'f.data_fechamento')->get();

        $total = 0.0;
        $porUnidade = [];
        foreach ($rows as $row) {
            $sis = $this->somaCampoFechamento($row->linhas_json, 'sis');
            $total += $sis;
            $nome = $row->unidade_nome ?? 'Unidade '.$row->unidade_id;
            $porUnidade[$nome] = ($porUnidade[$nome] ?? 0) + $sis;
        }

        return [
            'data' => $data,
            'faturamento_total' => round($total, 2),
            'por_unidade' => $porUnidade,
            'fechamentos' => $rows->count(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function comprasRecentes(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('listas_compras')) {
            return ['listas' => []];
        }

        $limite = min(20, max(1, (int) ($args['limite'] ?? 10)));

        $q = DB::table('listas_compras as l')
            ->leftJoin('unidades as u', 'l.unidade_id', '=', 'u.id')
            ->select('l.id', 'l.titulo', 'l.status', 'l.criado_em', 'u.nome as unidade_nome')
            ->orderByDesc('l.criado_em')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('l.unidade_id', $uid);
        }

        return [
            'listas' => $q->get()->map(fn ($l) => [
                'id' => $l->id,
                'titulo' => $l->titulo ?? 'Lista #'.$l->id,
                'status' => $l->status,
                'unidade' => $l->unidade_nome,
                'criado_em' => $l->criado_em,
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function fornecedores(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('fornecedores')) {
            return ['fornecedores' => []];
        }

        $busca = trim((string) ($args['busca'] ?? ''));
        $q = DB::table('fornecedores')->where('ativo', 1)->orderBy('nome')->limit(25);
        if ($busca !== '') {
            $q->where('nome', 'like', '%'.$busca.'%');
        }

        return [
            'fornecedores' => $q->get(['id', 'nome', 'telefone', 'email'])->map(fn ($f) => [
                'id' => $f->id,
                'nome' => $f->nome,
                'telefone' => $f->telefone ?? null,
                'email' => $f->email ?? null,
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function logsRecentes(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return ['logs' => []];
        }

        if (! in_array($ctx->perfil(), ['ADMIN', 'GERENTE'], true) && ! $ctx->temModulo('logs')) {
            return [
                'erro' => true,
                'mensagem' => 'Não encontrei informação suficiente ou você não tem permissão para acessar esse dado.',
            ];
        }

        $limite = min(50, max(5, (int) ($args['limite'] ?? 20)));

        $logs = DB::table('audit_logs as a')
            ->leftJoin('usuarios as u', 'a.usuario_id', '=', 'u.id')
            ->select('a.acao', 'a.recurso', 'a.descricao', 'a.created_at', 'u.nome as usuario_nome')
            ->orderByDesc('a.created_at')
            ->limit($limite)
            ->get();

        return [
            'logs' => $logs->map(fn ($l) => [
                'acao' => $l->acao,
                'recurso' => $l->recurso,
                'descricao' => $l->descricao,
                'usuario' => $l->usuario_nome,
                'quando' => $l->created_at,
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function resumoFinanceiro(SasIaContext $ctx, array $args): array
    {
        $pad = FinanceiroGerencialCalculo::periodoPadrao();
        $de = trim((string) ($args['de'] ?? $pad['de']));
        $ate = trim((string) ($args['ate'] ?? $pad['ate']));
        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();

        $dash = FinanceiroGerencialCalculo::consolidarPeriodo($de, $ate, $unidadeId);

        return [
            'periodo' => ['de' => $de, 'ate' => $ate],
            'faturamento' => $dash['faturamento_total'] ?? 0,
            'total_entradas' => $dash['total_entradas'] ?? 0,
            'total_saidas' => $dash['total_saidas'] ?? 0,
            'custo_saidas_estoque' => $dash['cmv_estimado'] ?? 0,
            'lucro_prejuizo' => $dash['lucro_prejuizo'] ?? 0,
            'margem_liquida_pct' => $dash['margem_liquida'] ?? 0,
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function resumoProdutos(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('produtos')) {
            return ['total_cadastrados' => 0, 'total_com_estoque' => 0, 'total_sem_estoque' => 0];
        }

        $totalCadastrados = (int) DB::table('produtos')->where('ativo', 1)->count();

        $comEstoqueQ = DB::table('stock_lotes')
            ->where('quantidade', '>', 0)
            ->distinct();
        if ($uid = $ctx->unidadeEfetiva()) {
            $comEstoqueQ->where('unidade_id', $uid);
        }
        $idsComEstoque = $comEstoqueQ->pluck('produto_id')->unique();
        $totalComEstoque = (int) DB::table('produtos')
            ->where('ativo', 1)
            ->whereIn('id', $idsComEstoque)
            ->count();

        $porUnidade = [];
        if (Schema::hasTable('stock_lotes') && Schema::hasTable('unidades') && ! $ctx->unidadeEfetiva()) {
            $porUnidade = DB::table('stock_lotes')
                ->join('unidades', 'stock_lotes.unidade_id', '=', 'unidades.id')
                ->where('stock_lotes.quantidade', '>', 0)
                ->select(
                    'unidades.id as unidade_id',
                    'unidades.nome as unidade_nome',
                    DB::raw('COUNT(DISTINCT stock_lotes.produto_id) as produtos_com_estoque')
                )
                ->groupBy('unidades.id', 'unidades.nome')
                ->orderBy('unidades.nome')
                ->limit(30)
                ->get()
                ->map(fn ($r) => [
                    'unidade_id' => $r->unidade_id,
                    'unidade_nome' => $r->unidade_nome,
                    'produtos_com_estoque' => (int) $r->produtos_com_estoque,
                ])
                ->all();
        }

        return [
            'total_cadastrados' => $totalCadastrados,
            'total_com_estoque' => $totalComEstoque,
            'total_sem_estoque' => max(0, $totalCadastrados - $totalComEstoque),
            'escopo_unidade_id' => $ctx->unidadeEfetiva(),
            'por_unidade_com_estoque' => $porUnidade,
            'observacao' => 'Cadastrados = produtos ativos no sistema. Com estoque = produtos com saldo > 0 em lotes.',
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function manualDocumentacao(SasIaContext $ctx, array $args): array
    {
        $consulta = trim((string) ($args['consulta'] ?? ''));

        return $this->documentService->buscarParaIa($consulta);
    }

    private function somaCampoFechamento(?string $json, string $campo): float
    {
        if (! $json) {
            return 0.0;
        }
        $linhas = json_decode($json, true);
        if (! is_array($linhas)) {
            return 0.0;
        }
        $s = 0.0;
        foreach ($linhas as $L) {
            if (is_array($L)) {
                $s += (float) ($L[$campo] ?? 0);
            }
        }

        return $s;
    }
}
