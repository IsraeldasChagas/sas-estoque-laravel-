<?php

namespace App\Services\Ayla;

use App\Support\Ayla\AylaSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas somente leitura do módulo Patrimônio para a Ayla.
 *
 * Espelha a query base das rotas existentes (patrimonios + categorias + unidades
 * + setores) sem alterar nenhum dado. Respeita as unidades permitidas na Ayla.
 */
class AylaPatrimonioService
{
    /** Situações reais do banco (coluna `situacao`, string). */
    public const SITUACOES = ['ativo', 'manutencao', 'baixado', 'vendido', 'quebrado'];

    /** Situações consideradas "baixadas" no dashboard. */
    private const SITUACOES_BAIXA = ['baixado', 'vendido', 'quebrado'];

    /**
     * Lista bens com filtros opcionais (somente leitura).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function consultar(array $filtros, ?int $userId = null): array
    {
        if (! Schema::hasTable('patrimonios')) {
            return [
                'bens' => [],
                'total' => 0,
                'retornados' => 0,
                'filtros_aplicados' => $this->filtrosAplicados($filtros),
            ];
        }

        $limite = min(50, max(1, (int) ($filtros['limite'] ?? $filtros['limit'] ?? 50)));

        $q = $this->queryBase();
        $this->aplicarFiltros($q, $filtros);

        $total = (int) (clone $q)->count();

        $rows = (clone $q)
            ->orderByDesc('p.updated_at')
            ->limit($limite)
            ->get();

        $bens = $rows->map(fn ($row) => $this->formatarBem($row))->values()->all();

        return [
            'bens' => $bens,
            'total' => $total,
            'retornados' => count($bens),
            'filtros_aplicados' => $this->filtrosAplicados($filtros),
        ];
    }

    /**
     * Detalhes completos de um bem (somente leitura).
     *
     * @return array<string, mixed>|null
     */
    public function detalhar(int $id): ?array
    {
        if (! Schema::hasTable('patrimonios')) {
            return null;
        }

        $q = $this->queryBase()->where('p.id', $id);
        $this->aplicarRestricaoUnidades($q);

        $row = $q->first();
        if (! $row) {
            return null;
        }

        $bem = $this->formatarBem($row, true);

        $bem['manutencoes'] = $this->manutencoesDoBem($id);
        $bem['movimentacoes'] = $this->movimentacoesDoBem($id);

        return $bem;
    }

    /**
     * Resumo patrimonial geral (ou de uma unidade específica).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function resumo(array $filtros = [], ?int $userId = null): array
    {
        if (! Schema::hasTable('patrimonios')) {
            return $this->resumoVazio();
        }

        $base = $this->queryBase();
        $this->aplicarFiltrosEscopo($base, $filtros);

        $total = (int) (clone $base)->count();
        $ativos = (int) (clone $base)->where('p.situacao', 'ativo')->count();
        $manutencao = (int) (clone $base)->where('p.situacao', 'manutencao')->count();
        $baixados = (int) (clone $base)->whereIn('p.situacao', self::SITUACOES_BAIXA)->count();
        $inativos = (int) (clone $base)->whereNotIn('p.situacao', ['ativo', 'manutencao'])->count();

        $semResponsavel = (int) (clone $base)
            ->where(function (Builder $qq) {
                $qq->whereNull('p.responsavel')->orWhere('p.responsavel', '');
            })
            ->count();

        $valorAquisicao = round((float) (clone $base)->sum('p.valor_compra'), 2);
        $valorAtual = round((float) (clone $base)->sum('p.valor_atual'), 2);

        $porUnidade = (clone $base)
            ->select(DB::raw('COALESCE(u.nome, "Sem unidade") as rotulo'), DB::raw('COUNT(*) as qtd'), DB::raw('COALESCE(SUM(p.valor_compra),0) as valor'))
            ->groupBy('rotulo')
            ->orderByDesc('qtd')
            ->get()
            ->map(fn ($r) => [
                'unidade' => (string) $r->rotulo,
                'quantidade' => (int) $r->qtd,
                'valor_aquisicao' => round((float) $r->valor, 2),
            ])
            ->all();

        $porCategoria = (clone $base)
            ->select(DB::raw('COALESCE(c.nome, "Sem categoria") as rotulo'), DB::raw('COUNT(*) as qtd'), DB::raw('COALESCE(SUM(p.valor_compra),0) as valor'))
            ->groupBy('rotulo')
            ->orderByDesc('qtd')
            ->get()
            ->map(fn ($r) => [
                'categoria' => (string) $r->rotulo,
                'quantidade' => (int) $r->qtd,
                'valor_aquisicao' => round((float) $r->valor, 2),
            ])
            ->all();

        $aquisicoesRecentes = (clone $base)
            ->whereNotNull('p.data_compra')
            ->orderByDesc('p.data_compra')
            ->limit(5)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        $maisAntigos = (clone $base)
            ->whereNotNull('p.data_compra')
            ->orderBy('p.data_compra')
            ->limit(5)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        $maiorValor = (clone $base)
            ->whereNotNull('p.valor_compra')
            ->orderByDesc('p.valor_compra')
            ->limit(5)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        return [
            'total_bens' => $total,
            'total_ativo' => $ativos,
            'total_manutencao' => $manutencao,
            'total_baixado' => $baixados,
            'total_inativo' => $inativos,
            'bens_sem_responsavel' => $semResponsavel,
            'valor_total_aquisicao' => $valorAquisicao,
            'valor_total_atual' => $valorAtual,
            'por_unidade' => $porUnidade,
            'por_categoria' => $porCategoria,
            'aquisicoes_recentes' => $aquisicoesRecentes,
            'bens_mais_antigos' => $maisAntigos,
            'bens_maior_valor' => $maiorValor,
            'alertas' => $this->alertas($filtros, $userId),
        ];
    }

    /**
     * Resumo + lista de bens de uma unidade.
     *
     * @return array<string, mixed>
     */
    public function porUnidade(int $unidadeId, ?int $userId = null): array
    {
        $unidade = Schema::hasTable('unidades')
            ? DB::table('unidades')->where('id', $unidadeId)->first(['id', 'nome'])
            : null;

        $filtros = ['unidade_id' => $unidadeId];

        return [
            'unidade' => $unidade ? ['id' => (int) $unidade->id, 'nome' => (string) $unidade->nome] : ['id' => $unidadeId, 'nome' => null],
            'resumo' => $this->resumo($filtros, $userId),
            'bens' => $this->consultar($filtros + ['limite' => 50], $userId)['bens'],
        ];
    }

    /**
     * Alertas patrimoniais (garantia, manutenção, sem responsável, etc.).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function alertas(array $filtros = [], ?int $userId = null): array
    {
        if (! Schema::hasTable('patrimonios')) {
            return $this->alertasVazio();
        }

        $hoje = Carbon::today()->toDateString();
        $em30 = Carbon::today()->addDays(30)->toDateString();

        $base = $this->queryBase();
        $this->aplicarFiltrosEscopo($base, $filtros);

        $semResponsavel = (clone $base)
            ->where(function (Builder $qq) {
                $qq->whereNull('p.responsavel')->orWhere('p.responsavel', '');
            })
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        $semUnidade = (clone $base)
            ->whereNull('p.unidade_id')
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        $semCodigo = (clone $base)
            ->where(function (Builder $qq) {
                $qq->whereNull('p.codigo')->orWhere('p.codigo', '');
            })
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        $irregulares = (clone $base)
            ->whereIn('p.situacao', ['manutencao', 'quebrado'])
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarBem($r))
            ->all();

        // Manutenção: usa patrimonio_manutencoes.proxima_manutencao (por bem).
        $manutencaoProxima = [];
        $manutencaoAtrasada = [];
        if (Schema::hasTable('patrimonio_manutencoes')) {
            $permitidas = AylaSettings::unidadesPermitidas();

            $baseMan = DB::table('patrimonio_manutencoes as m')
                ->join('patrimonios as p', 'p.id', '=', 'm.patrimonio_id')
                ->leftJoin('unidades as u', 'u.id', '=', 'p.unidade_id')
                ->whereNotNull('m.proxima_manutencao');

            if ($permitidas !== []) {
                $baseMan->where(function (Builder $qq) use ($permitidas) {
                    $qq->whereIn('p.unidade_id', $permitidas)->orWhereNull('p.unidade_id');
                });
            }
            $unidadeFiltro = $this->unidadeDoFiltro($filtros);
            if ($unidadeFiltro) {
                $baseMan->where('p.unidade_id', $unidadeFiltro);
            }

            $cols = ['p.id', 'p.codigo', 'p.nome', 'u.nome as unidade_nome', 'm.proxima_manutencao', 'm.tipo_manutencao'];

            $manutencaoProxima = (clone $baseMan)
                ->whereDate('m.proxima_manutencao', '>=', $hoje)
                ->whereDate('m.proxima_manutencao', '<=', $em30)
                ->orderBy('m.proxima_manutencao')
                ->limit(50)
                ->get($cols)
                ->map(fn ($r) => $this->formatarAlertaManutencao($r))
                ->all();

            $manutencaoAtrasada = (clone $baseMan)
                ->where('p.situacao', '!=', 'baixado')
                ->whereDate('m.proxima_manutencao', '<', $hoje)
                ->orderBy('m.proxima_manutencao')
                ->limit(50)
                ->get($cols)
                ->map(fn ($r) => $this->formatarAlertaManutencao($r))
                ->all();
        }

        // Garantia: não há coluna dedicada. Usa dados_especificos->vencimento_garantia
        // quando existir (JSON). Filtra em PHP para compatibilidade entre bancos.
        [$garantiaProxima, $garantiaVencida] = $this->alertasGarantia($base, $hoje, $em30);

        return [
            'garantia_proxima' => $garantiaProxima,
            'garantia_vencida' => $garantiaVencida,
            'manutencao_proxima' => $manutencaoProxima,
            'manutencao_atrasada' => $manutencaoAtrasada,
            'bens_sem_responsavel' => $semResponsavel,
            'bens_sem_unidade' => $semUnidade,
            'bens_sem_codigo' => $semCodigo,
            'bens_situacao_irregular' => $irregulares,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    private function queryBase(): Builder
    {
        return DB::table('patrimonios as p')
            ->leftJoin('patrimonio_categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('unidades as u', 'u.id', '=', 'p.unidade_id')
            ->select([
                'p.id', 'p.codigo', 'p.nome', 'p.numero_serial', 'p.marca', 'p.modelo',
                'p.cor', 'p.quantidade', 'p.categoria_id', 'p.unidade_id', 'p.setor_id',
                'p.setor', 'p.responsavel', 'p.funcionario_id', 'p.situacao',
                'p.valor_compra', 'p.data_compra', 'p.vida_util_meses', 'p.valor_atual',
                'p.depreciacao', 'p.fornecedor', 'p.numero_nf', 'p.observacoes',
                'p.dados_especificos', 'p.created_at', 'p.updated_at',
                'c.nome as categoria_nome', 'c.tipo_campos as categoria_tipo',
                'u.nome as unidade_nome',
            ]);
    }

    private function aplicarRestricaoUnidades(Builder $q): void
    {
        $permitidas = AylaSettings::unidadesPermitidas();
        if ($permitidas === []) {
            return;
        }
        $q->where(function (Builder $qq) use ($permitidas) {
            $qq->whereIn('p.unidade_id', $permitidas)->orWhereNull('p.unidade_id');
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $q, array $filtros): void
    {
        $this->aplicarFiltrosEscopo($q, $filtros);

        if (! empty($filtros['patrimonio_id'])) {
            $q->where('p.id', (int) $filtros['patrimonio_id']);
        }

        if (! empty($filtros['status'])) {
            $q->where('p.situacao', (string) $filtros['status']);
        }

        if (! empty($filtros['responsavel'])) {
            $q->where('p.responsavel', 'like', '%'.trim((string) $filtros['responsavel']).'%');
        }

        if (! empty($filtros['valor_minimo'])) {
            $q->where('p.valor_compra', '>=', (float) $filtros['valor_minimo']);
        }

        if (! empty($filtros['valor_maximo'])) {
            $q->where('p.valor_compra', '<=', (float) $filtros['valor_maximo']);
        }

        if (! empty($filtros['data_inicio'])) {
            $q->whereDate('p.data_compra', '>=', (string) $filtros['data_inicio']);
        }

        if (! empty($filtros['data_fim'])) {
            $q->whereDate('p.data_compra', '<=', (string) $filtros['data_fim']);
        }

        if (! empty($filtros['busca'])) {
            $termos = preg_split('/\s+/', trim((string) $filtros['busca'])) ?: [];
            foreach ($termos as $termo) {
                if ($termo === '') {
                    continue;
                }
                $like = '%'.$termo.'%';
                $q->where(function (Builder $qq) use ($like) {
                    $qq->where('p.nome', 'like', $like)
                        ->orWhere('p.codigo', 'like', $like)
                        ->orWhere('p.numero_serial', 'like', $like)
                        ->orWhere('p.responsavel', 'like', $like)
                        ->orWhere('p.setor', 'like', $like)
                        ->orWhere('p.marca', 'like', $like)
                        ->orWhere('p.modelo', 'like', $like)
                        ->orWhere('p.fornecedor', 'like', $like)
                        ->orWhere('c.nome', 'like', $like)
                        ->orWhere('u.nome', 'like', $like);
                });
            }
        }
    }

    /**
     * Filtros de escopo (unidade, categoria, setor) reaproveitados em resumo/alertas.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosEscopo(Builder $q, array $filtros): void
    {
        $this->aplicarRestricaoUnidades($q);

        $unidadeId = $this->unidadeDoFiltro($filtros);
        if ($unidadeId) {
            $q->where('p.unidade_id', $unidadeId);
        }

        if (! empty($filtros['categoria'])) {
            $cat = trim((string) $filtros['categoria']);
            if (ctype_digit($cat)) {
                $q->where('p.categoria_id', (int) $cat);
            } else {
                $q->where('c.nome', 'like', '%'.$cat.'%');
            }
        }

        if (! empty($filtros['categoria_id'])) {
            $q->where('p.categoria_id', (int) $filtros['categoria_id']);
        }

        if (! empty($filtros['setor'])) {
            $setor = trim((string) $filtros['setor']);
            if (ctype_digit($setor)) {
                $q->where('p.setor_id', (int) $setor);
            } else {
                $q->where('p.setor', 'like', '%'.$setor.'%');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function unidadeDoFiltro(array $filtros): ?int
    {
        if (! empty($filtros['unidade_id']) && (int) $filtros['unidade_id'] > 0) {
            return (int) $filtros['unidade_id'];
        }

        return null;
    }

    /**
     * Resolve nome de unidade para ID.
     */
    public function resolverUnidadeIdPorNome(string $nome): ?int
    {
        $nome = trim($nome);
        if ($nome === '' || ! Schema::hasTable('unidades')) {
            return null;
        }

        $row = DB::table('unidades')
            ->where('nome', 'like', '%'.$nome.'%')
            ->orderByRaw('CASE WHEN nome = ? THEN 0 ELSE 1 END', [$nome])
            ->first(['id']);

        return $row ? (int) $row->id : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manutencoesDoBem(int $id): array
    {
        if (! Schema::hasTable('patrimonio_manutencoes')) {
            return [];
        }

        return DB::table('patrimonio_manutencoes')
            ->where('patrimonio_id', $id)
            ->orderByDesc('data_manutencao')
            ->limit(10)
            ->get(['id', 'tipo_manutencao', 'descricao', 'tecnico', 'custo', 'data_manutencao', 'proxima_manutencao'])
            ->map(fn ($r) => [
                'tipo' => (string) $r->tipo_manutencao,
                'descricao' => $r->descricao ? (string) $r->descricao : null,
                'tecnico' => $r->tecnico ? (string) $r->tecnico : null,
                'custo' => $r->custo !== null ? round((float) $r->custo, 2) : null,
                'data' => $r->data_manutencao ? substr((string) $r->data_manutencao, 0, 10) : null,
                'proxima' => $r->proxima_manutencao ? substr((string) $r->proxima_manutencao, 0, 10) : null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function movimentacoesDoBem(int $id): array
    {
        if (! Schema::hasTable('patrimonio_movimentacoes')) {
            return [];
        }

        return DB::table('patrimonio_movimentacoes')
            ->where('patrimonio_id', $id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['tipo', 'unidade_origem_id', 'unidade_destino_id', 'responsavel_anterior', 'responsavel_novo', 'observacao', 'created_at'])
            ->map(fn ($r) => [
                'tipo' => (string) $r->tipo,
                'unidade_origem_id' => $r->unidade_origem_id ? (int) $r->unidade_origem_id : null,
                'unidade_destino_id' => $r->unidade_destino_id ? (int) $r->unidade_destino_id : null,
                'responsavel_anterior' => $r->responsavel_anterior ? (string) $r->responsavel_anterior : null,
                'responsavel_novo' => $r->responsavel_novo ? (string) $r->responsavel_novo : null,
                'observacao' => $r->observacao ? (string) $r->observacao : null,
                'data' => $r->created_at ? (string) $r->created_at : null,
            ])
            ->all();
    }

    /**
     * Alertas de garantia via dados_especificos->vencimento_garantia (JSON opcional).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function alertasGarantia(Builder $base, string $hoje, string $em30): array
    {
        $proxima = [];
        $vencida = [];

        $rows = (clone $base)
            ->whereNotNull('p.dados_especificos')
            ->limit(500)
            ->get(['p.id', 'p.codigo', 'p.nome', 'p.dados_especificos', 'u.nome as unidade_nome']);

        foreach ($rows as $r) {
            $dados = json_decode((string) $r->dados_especificos, true);
            if (! is_array($dados)) {
                continue;
            }
            $venc = $dados['vencimento_garantia'] ?? $dados['garantia'] ?? null;
            if (! is_string($venc) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $venc)) {
                continue;
            }
            $data = substr($venc, 0, 10);
            $item = [
                'id' => (int) $r->id,
                'codigo' => (string) $r->codigo,
                'nome' => (string) $r->nome,
                'unidade' => $r->unidade_nome ? (string) $r->unidade_nome : null,
                'vencimento_garantia' => $data,
            ];
            if ($data < $hoje) {
                $vencida[] = $item;
            } elseif ($data <= $em30) {
                $proxima[] = $item;
            }
        }

        return [$proxima, $vencida];
    }

    /** @return array<string, mixed> */
    private function formatarAlertaManutencao(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'codigo' => (string) $r->codigo,
            'nome' => (string) $r->nome,
            'unidade' => $r->unidade_nome ? (string) $r->unidade_nome : null,
            'proxima_manutencao' => $r->proxima_manutencao ? substr((string) $r->proxima_manutencao, 0, 10) : null,
            'tipo' => $r->tipo_manutencao ? (string) $r->tipo_manutencao : null,
        ];
    }

    /** @return array<string, mixed> */
    private function formatarBem(object $row, bool $completo = false): array
    {
        $bem = [
            'id' => (int) $row->id,
            'codigo' => $row->codigo ? (string) $row->codigo : null,
            'nome' => (string) $row->nome,
            'categoria' => $row->categoria_nome ? (string) $row->categoria_nome : null,
            'categoria_id' => $row->categoria_id ? (int) $row->categoria_id : null,
            'situacao' => (string) $row->situacao,
            'unidade' => $row->unidade_nome ? (string) $row->unidade_nome : null,
            'unidade_id' => $row->unidade_id ? (int) $row->unidade_id : null,
            'setor' => $row->setor ? (string) $row->setor : null,
            'responsavel' => $row->responsavel ? (string) $row->responsavel : null,
            'marca' => $row->marca ? (string) $row->marca : null,
            'modelo' => $row->modelo ? (string) $row->modelo : null,
            'numero_serial' => $row->numero_serial ? (string) $row->numero_serial : null,
            'valor_compra' => $row->valor_compra !== null ? round((float) $row->valor_compra, 2) : null,
            'valor_atual' => $row->valor_atual !== null ? round((float) $row->valor_atual, 2) : null,
            'data_compra' => $row->data_compra ? substr((string) $row->data_compra, 0, 10) : null,
        ];

        if ($completo) {
            $bem['cor'] = $row->cor ? (string) $row->cor : null;
            $bem['quantidade'] = (int) ($row->quantidade ?? 1);
            $bem['vida_util_meses'] = $row->vida_util_meses ? (int) $row->vida_util_meses : null;
            $bem['depreciacao'] = $row->depreciacao !== null ? round((float) $row->depreciacao, 2) : null;
            $bem['fornecedor'] = $row->fornecedor ? (string) $row->fornecedor : null;
            $bem['numero_nf'] = $row->numero_nf ? (string) $row->numero_nf : null;
            $bem['observacoes'] = $row->observacoes ? (string) $row->observacoes : null;
            $bem['categoria_tipo'] = $row->categoria_tipo ? (string) $row->categoria_tipo : null;
            $dados = $row->dados_especificos ? json_decode((string) $row->dados_especificos, true) : null;
            $bem['dados_especificos'] = is_array($dados) ? $dados : null;
            $bem['criado_em'] = $row->created_at ? (string) $row->created_at : null;
            $bem['atualizado_em'] = $row->updated_at ? (string) $row->updated_at : null;
        }

        return $bem;
    }

    /** @return array<string, mixed> */
    private function resumoVazio(): array
    {
        return [
            'total_bens' => 0,
            'total_ativo' => 0,
            'total_manutencao' => 0,
            'total_baixado' => 0,
            'total_inativo' => 0,
            'bens_sem_responsavel' => 0,
            'valor_total_aquisicao' => 0.0,
            'valor_total_atual' => 0.0,
            'por_unidade' => [],
            'por_categoria' => [],
            'aquisicoes_recentes' => [],
            'bens_mais_antigos' => [],
            'bens_maior_valor' => [],
            'alertas' => $this->alertasVazio(),
        ];
    }

    /** @return array<string, array<mixed>> */
    private function alertasVazio(): array
    {
        return [
            'garantia_proxima' => [],
            'garantia_vencida' => [],
            'manutencao_proxima' => [],
            'manutencao_atrasada' => [],
            'bens_sem_responsavel' => [],
            'bens_sem_unidade' => [],
            'bens_sem_codigo' => [],
            'bens_situacao_irregular' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosAplicados(array $filtros): array
    {
        $out = [];
        foreach (['busca', 'patrimonio_id', 'unidade_id', 'categoria', 'categoria_id', 'status', 'responsavel', 'setor', 'data_inicio', 'data_fim', 'valor_minimo', 'valor_maximo', 'limite', 'limit'] as $k) {
            if (isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null) {
                $out[$k] = $filtros[$k];
            }
        }

        return $out;
    }
}
