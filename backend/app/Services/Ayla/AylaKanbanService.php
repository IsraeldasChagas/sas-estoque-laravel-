<?php

namespace App\Services\Ayla;

use App\Support\Ayla\AylaSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas somente leitura do Kanban Administrativo para a Ayla.
 * Nunca altera dados.
 */
class AylaKanbanService
{
  private const STATUS_DB = ['planejamento', 'a_fazer', 'em_execucao', 'aguardando', 'finalizado'];

  private const PRIORIDADE_DB = ['baixa', 'media', 'alta'];

  /** Aliases amigáveis → status do banco ou flag especial. */
  private const STATUS_ALIASES = [
    'pendente' => ['a_fazer', 'planejamento'],
    'pendentes' => ['a_fazer', 'planejamento'],
    'a_fazer' => ['a_fazer'],
    'planejamento' => ['planejamento'],
    'em_andamento' => ['em_execucao'],
    'em andamento' => ['em_execucao'],
    'andamento' => ['em_execucao'],
    'em_execucao' => ['em_execucao'],
    'concluida' => ['finalizado'],
    'concluidas' => ['finalizado'],
    'concluido' => ['finalizado'],
    'concluidos' => ['finalizado'],
    'finalizado' => ['finalizado'],
    'finalizada' => ['finalizado'],
    'finalizadas' => ['finalizado'],
    'bloqueada' => ['aguardando'],
    'bloqueadas' => ['aguardando'],
    'bloqueado' => ['aguardando'],
    'aguardando' => ['aguardando'],
    'atrasado' => '__ATRASADO__',
    'atrasada' => '__ATRASADO__',
    'atrasadas' => '__ATRASADO__',
  ];

  /** Aliases de setor para perguntas naturais. */
  private const SETOR_ALIASES = [
    'rh' => 'RH',
    'financeiro' => 'Financeiro',
    'estoque' => 'Estoque',
    'compras' => 'Compras',
    'producao' => 'Cozinha',
    'produção' => 'Cozinha',
    'cozinha' => 'Cozinha',
    'administrativo' => 'Administrativo',
    'marketing' => 'Marketing',
    'manutencao' => 'Manutenção',
    'manutenção' => 'Manutenção',
    'geral' => 'Geral',
  ];

    /**
     * Resolve nome de unidade para ID (uso na validação de permissão do controller).
     */
    public function resolverUnidadeIdPorNome(string $nome): ?int
    {
        return $this->resolverUnidadeId(['unidade' => $nome]);
    }

    /**
   *
   * @param  array<string, mixed>  $filtros
   * @return array<string, mixed>
   */
  public function consultar(array $filtros, ?int $userId = null): array
  {
    if (! Schema::hasTable('kanban_tasks')) {
      return [
        'tarefas' => [],
        'total' => 0,
        'filtros_aplicados' => $this->filtrosAplicados($filtros),
        'resumo' => $this->resumoVazio(),
      ];
    }

    $limite = min(50, max(1, (int) ($filtros['limit'] ?? $filtros['limite'] ?? 50)));
    $q = $this->queryBase($filtros, $userId);
    $this->aplicarFiltros($q, $filtros);

    $total = (int) (clone $q)->count();

    $rows = (clone $q)
      ->orderByRaw('prazo IS NULL')
      ->orderBy('prazo')
      ->orderByDesc('updated_at')
      ->limit($limite)
      ->get();

    $tarefas = $rows->map(fn ($row) => $this->formatarTarefa($row))->values()->all();

    $resumoFiltros = $filtros;
    unset($resumoFiltros['limit'], $resumoFiltros['limite']);

    return [
      'tarefas' => $tarefas,
      'total' => $total,
      'retornadas' => count($tarefas),
      'filtros_aplicados' => $this->filtrosAplicados($filtros),
      'resumo' => $this->kanbanResumo($resumoFiltros, $userId),
    ];
  }

  /**
   * Resumo agregado do kanban (contagens).
   *
   * @param  array<string, mixed>  $filtros
   * @return array<string, mixed>
   */
  public function kanbanResumo(array $filtros = [], ?int $userId = null): array
  {
    if (! Schema::hasTable('kanban_tasks')) {
      return $this->resumoVazio();
    }

    $hoje = Carbon::today()->toDateString();
    $amanha = Carbon::tomorrow()->toDateString();

    $base = $this->queryBase($filtros, $userId);
    $this->aplicarFiltrosEscopo($base, $filtros);

    $total = (int) (clone $base)->count();

    $pendentes = (int) (clone $base)->whereIn('status', ['planejamento', 'a_fazer'])->count();
    $emAndamento = (int) (clone $base)->where('status', 'em_execucao')->count();
    $concluidas = (int) (clone $base)->where('status', 'finalizado')->count();
    $bloqueadas = (int) (clone $base)->where('status', 'aguardando')->count();

    $atrasadas = (int) (clone $base)
      ->where('status', '!=', 'finalizado')
      ->whereNotNull('prazo')
      ->whereDate('prazo', '<', $hoje)
      ->count();

    $vencemHoje = (int) (clone $base)
      ->where('status', '!=', 'finalizado')
      ->whereDate('prazo', $hoje)
      ->count();

    $vencemAmanha = (int) (clone $base)
      ->where('status', '!=', 'finalizado')
      ->whereDate('prazo', $amanha)
      ->count();

    $prioridadeAlta = (int) (clone $base)
      ->where('prioridade', 'alta')
      ->where('status', '!=', 'finalizado')
      ->count();

    $concluidasHoje = (int) (clone $base)
      ->where('status', 'finalizado')
      ->whereDate('updated_at', $hoje)
      ->count();

    $maisAntigas = (clone $base)
      ->where('status', '!=', 'finalizado')
      ->orderBy('created_at')
      ->limit(3)
      ->get(['id', 'titulo', 'setor', 'responsavel', 'prazo', 'status', 'created_at'])
      ->map(fn ($row) => $this->formatarTarefa($row))
      ->values()
      ->all();

    return [
      'total' => $total,
      'pendentes' => $pendentes,
      'em_andamento' => $emAndamento,
      'concluidas' => $concluidas,
      'bloqueadas' => $bloqueadas,
      'atrasadas' => $atrasadas,
      'vencem_hoje' => $vencemHoje,
      'vencem_amanha' => $vencemAmanha,
      'prioridade_alta' => $prioridadeAlta,
      'concluidas_hoje' => $concluidasHoje,
      'mais_antigas' => $maisAntigas,
      'por_status' => (clone $base)
        ->select('status', DB::raw('COUNT(*) as qtd'))
        ->groupBy('status')
        ->pluck('qtd', 'status')
        ->all(),
    ];
  }

  /** @return array<string, int> */
  private function resumoVazio(): array
  {
    return [
      'total' => 0,
      'pendentes' => 0,
      'em_andamento' => 0,
      'concluidas' => 0,
      'bloqueadas' => 0,
      'atrasadas' => 0,
      'vencem_hoje' => 0,
      'vencem_amanha' => 0,
      'prioridade_alta' => 0,
      'concluidas_hoje' => 0,
      'mais_antigas' => [],
      'por_status' => [],
    ];
  }

  /**
   * Query base com join de unidade e restrição de unidades permitidas pela Ayla.
   *
   * @param  array<string, mixed>  $filtros
   */
  private function queryBase(array $filtros, ?int $userId): Builder
  {
    $q = DB::table('kanban_tasks as k')
      ->leftJoin('unidades as u', 'u.id', '=', 'k.unidade_id')
      ->select([
        'k.id', 'k.titulo', 'k.descricao', 'k.setor', 'k.responsavel',
        'k.prioridade', 'k.status', 'k.prazo', 'k.observacoes',
        'k.unidade_id', 'k.created_at', 'k.updated_at',
        'u.nome as unidade_nome',
      ]);

    $this->aplicarRestricaoUnidades($q);

    return $q;
  }

  private function aplicarRestricaoUnidades(Builder $q): void
  {
    $permitidas = AylaSettings::unidadesPermitidas();
    if ($permitidas === []) {
      return;
    }

    $q->where(function (Builder $qq) use ($permitidas) {
      $qq->whereIn('k.unidade_id', $permitidas)
        ->orWhereNull('k.unidade_id');
    });
  }

  /**
   * @param  array<string, mixed>  $filtros
   */
  private function aplicarFiltros(Builder $q, array $filtros): void
  {
    $this->aplicarFiltrosEscopo($q, $filtros);

    if (! empty($filtros['status'])) {
      $this->aplicarFiltroStatus($q, (string) $filtros['status']);
    }

    if (! empty($filtros['prioridade'])) {
      $prio = $this->normalizarPrioridade((string) $filtros['prioridade']);
      if ($prio) {
        $q->where('k.prioridade', $prio);
      }
    }

    if (! empty($filtros['responsavel'])) {
      $q->where('k.responsavel', 'like', '%'.trim((string) $filtros['responsavel']).'%');
    }

    if (! empty($filtros['texto'])) {
      $t = trim((string) $filtros['texto']);
      $q->where(function (Builder $qq) use ($t) {
        $qq->where('k.titulo', 'like', "%{$t}%")
          ->orWhere('k.descricao', 'like', "%{$t}%")
          ->orWhere('k.observacoes', 'like', "%{$t}%");
      });
    }

    if (! empty($filtros['vencimento'])) {
      $this->aplicarFiltroVencimento($q, (string) $filtros['vencimento']);
    }

    if (! empty($filtros['data'])) {
      $data = $this->parseData((string) $filtros['data']);
      if ($data) {
        $q->whereDate('k.prazo', $data);
      }
    }
  }

  /**
   * Filtros de escopo (unidade, setor) sem status/prioridade.
   *
   * @param  array<string, mixed>  $filtros
   */
  private function aplicarFiltrosEscopo(Builder $q, array $filtros): void
  {
    $unidadeId = $this->resolverUnidadeId($filtros);
    if ($unidadeId) {
      $q->where(function (Builder $qq) use ($unidadeId) {
        $qq->where('k.unidade_id', $unidadeId)->orWhereNull('k.unidade_id');
      });
    }

    if (! empty($filtros['setor'])) {
      $setor = $this->normalizarSetor((string) $filtros['setor']);
      if ($setor) {
        $q->where('k.setor', $setor);
      }
    }
  }

  private function aplicarFiltroStatus(Builder $q, string $status): void
  {
    $chave = mb_strtolower(trim($status));
    $chave = str_replace([' ', '-'], '_', $chave);

    if ($chave === '__atrasado__' || in_array($chave, ['atrasado', 'atrasada', 'atrasadas'], true)) {
      $hoje = Carbon::today()->toDateString();
      $q->where('k.status', '!=', 'finalizado')
        ->whereNotNull('k.prazo')
        ->whereDate('k.prazo', '<', $hoje);

      return;
    }

    $alias = self::STATUS_ALIASES[$chave] ?? null;
    if ($alias === '__ATRASADO__') {
      $hoje = Carbon::today()->toDateString();
      $q->where('k.status', '!=', 'finalizado')
        ->whereNotNull('k.prazo')
        ->whereDate('k.prazo', '<', $hoje);

      return;
    }

    if (is_array($alias)) {
      $q->whereIn('k.status', $alias);

      return;
    }

    if (in_array($chave, self::STATUS_DB, true)) {
      $q->where('k.status', $chave);
    }
  }

  private function aplicarFiltroVencimento(Builder $q, string $vencimento): void
  {
    $v = mb_strtolower(trim($vencimento));
    $hoje = Carbon::today()->toDateString();
    $amanha = Carbon::tomorrow()->toDateString();

    match ($v) {
      'hoje' => $q->whereDate('k.prazo', $hoje)->where('k.status', '!=', 'finalizado'),
      'amanha', 'amanhã' => $q->whereDate('k.prazo', $amanha)->where('k.status', '!=', 'finalizado'),
      'atrasado', 'atrasada', 'atrasadas' => $q->where('k.status', '!=', 'finalizado')
        ->whereNotNull('k.prazo')
        ->whereDate('k.prazo', '<', $hoje),
      default => null,
    };
  }

  /**
   * @param  array<string, mixed>  $filtros
   */
  private function resolverUnidadeId(array $filtros): ?int
  {
    if (! empty($filtros['unidade_id']) && (int) $filtros['unidade_id'] > 0) {
      return (int) $filtros['unidade_id'];
    }

    $nome = trim((string) ($filtros['unidade'] ?? ''));
    if ($nome === '' || ! Schema::hasTable('unidades')) {
      return null;
    }

    $row = DB::table('unidades')
      ->where('nome', 'like', '%'.$nome.'%')
      ->orderByRaw('CASE WHEN nome = ? THEN 0 ELSE 1 END', [$nome])
      ->first(['id']);

    return $row ? (int) $row->id : null;
  }

  private function normalizarPrioridade(string $prioridade): ?string
  {
    $p = mb_strtolower(trim($prioridade));
    $map = [
      'alta' => 'alta',
      'média' => 'media',
      'media' => 'media',
      'baixa' => 'baixa',
    ];

    return $map[$p] ?? (in_array($p, self::PRIORIDADE_DB, true) ? $p : null);
  }

  private function normalizarSetor(string $setor): ?string
  {
    $s = mb_strtolower(trim($setor));
    if (isset(self::SETOR_ALIASES[$s])) {
      return self::SETOR_ALIASES[$s];
    }

    return trim($setor) !== '' ? trim($setor) : null;
  }

  private function parseData(string $data): ?string
  {
    $data = trim($data);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
      return $data;
    }

    return null;
  }

  /** @return array<string, mixed> */
  private function formatarTarefa(object $row): array
  {
    $prazo = $row->prazo ? (string) $row->prazo : null;
    $hoje = Carbon::today()->toDateString();
    $atrasada = $prazo && $row->status !== 'finalizado' && substr($prazo, 0, 10) < $hoje;

    return [
      'id' => (int) $row->id,
      'titulo' => (string) $row->titulo,
      'descricao' => $row->descricao ? (string) $row->descricao : null,
      'setor' => (string) $row->setor,
      'responsavel' => $row->responsavel ? (string) $row->responsavel : null,
      'prioridade' => (string) $row->prioridade,
      'status' => (string) $row->status,
      'prazo' => $prazo ? substr($prazo, 0, 10) : null,
      'atrasada' => $atrasada,
      'unidade_id' => $row->unidade_id ? (int) $row->unidade_id : null,
      'unidade' => $row->unidade_nome ? (string) $row->unidade_nome : null,
      'observacoes' => $row->observacoes ? (string) $row->observacoes : null,
      'criada_em' => $row->created_at ? (string) $row->created_at : null,
      'atualizada_em' => $row->updated_at ? (string) $row->updated_at : null,
    ];
  }

  /**
   * @param  array<string, mixed>  $filtros
   * @return array<string, mixed>
   */
  private function filtrosAplicados(array $filtros): array
  {
    $out = [];
    foreach (['status', 'prioridade', 'responsavel', 'unidade', 'unidade_id', 'setor', 'data', 'vencimento', 'texto', 'limit', 'limite'] as $k) {
      if (! empty($filtros[$k])) {
        $out[$k] = $filtros[$k];
      }
    }

    return $out;
  }
}
