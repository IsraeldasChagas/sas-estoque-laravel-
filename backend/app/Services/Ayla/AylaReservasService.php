<?php

namespace App\Services\Ayla;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use App\Support\Ayla\AylaSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Consultas e escrita controlada do módulo Reservas de Mesas para a Ayla.
 *
 * Leitura: consultar/resumo/disponibilidade/alertas.
 * Escrita: somente via prepararPreview + executarAcaoConfirmada (após confirmação).
 *
 * Regras alinhadas a ReservaMesaController (conflito exato, capacidade, status reais).
 */
class AylaReservasService
{
    /** Status reais de reserva (coluna `reservas_mesas.status`). */
    public const STATUS_RESERVA = [
        'pendente', 'confirmada', 'cancelada', 'cliente_chegou', 'no_show', 'finalizada',
    ];

    /** Status que ainda “ocupam” o slot (ativas). */
    private const STATUS_ATIVAS = ['pendente', 'confirmada', 'cliente_chegou'];

    /** Status reais de mesa. */
    public const STATUS_MESA = [
        'livre', 'reservada', 'aguardando_cliente', 'ocupada', 'bloqueada',
    ];

    /**
     * Lista reservas com filtros.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function consultar(array $filtros, ?int $userId = null): array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return [
                'reservas' => [],
                'total' => 0,
                'retornadas' => 0,
                'filtros_aplicados' => $this->filtrosAplicados($filtros),
            ];
        }

        $limite = min(50, max(1, (int) ($filtros['limite'] ?? $filtros['limit'] ?? 50)));
        $q = $this->queryReservas();
        $this->aplicarFiltrosReserva($q, $filtros);

        $total = (int) (clone $q)->count();
        $rows = (clone $q)
            ->orderBy('r.data_reserva')
            ->orderBy('r.hora_reserva')
            ->limit($limite)
            ->get();

        $reservas = $rows->map(fn ($row) => $this->formatarReserva($row))->values()->all();

        return [
            'reservas' => $reservas,
            'total' => $total,
            'retornadas' => count($reservas),
            'filtros_aplicados' => $this->filtrosAplicados($filtros),
        ];
    }

    /**
     * Detalhe de uma reserva.
     *
     * @return array<string, mixed>|null
     */
    public function detalhar(int $id): ?array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return null;
        }

        $q = $this->queryReservas()->where('r.id', $id);
        $this->aplicarRestricaoUnidades($q, 'r.unidade_id');
        $row = $q->first();

        return $row ? $this->formatarReserva($row, true) : null;
    }

    /**
     * Resumo agregado de reservas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function resumo(array $filtros = [], ?int $userId = null): array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return $this->resumoVazio();
        }

        $hoje = Carbon::today()->toDateString();
        $amanha = Carbon::tomorrow()->toDateString();
        $agora = Carbon::now();
        $em60 = Carbon::now()->addMinutes(60);

        $base = $this->queryReservas();
        $this->aplicarFiltrosEscopo($base, $filtros);

        $total = (int) (clone $base)->count();
        $hojeN = (int) (clone $base)->whereDate('r.data_reserva', $hoje)->count();
        $amanhaN = (int) (clone $base)->whereDate('r.data_reserva', $amanha)->count();

        $pendentes = (int) (clone $base)->where('r.status', 'pendente')->count();
        $confirmadas = (int) (clone $base)->where('r.status', 'confirmada')->count();
        $concluidas = (int) (clone $base)->whereIn('r.status', ['finalizada', 'cliente_chegou'])->count();
        $canceladas = (int) (clone $base)->whereIn('r.status', ['cancelada', 'no_show'])->count();

        $pessoasHoje = (int) (clone $base)
            ->whereDate('r.data_reserva', $hoje)
            ->whereIn('r.status', self::STATUS_ATIVAS)
            ->sum('r.qtd_pessoas');

        $proximas = (clone $base)
            ->whereDate('r.data_reserva', $hoje)
            ->whereIn('r.status', ['pendente', 'confirmada'])
            ->whereTime('r.hora_reserva', '>=', $agora->format('H:i:s'))
            ->whereTime('r.hora_reserva', '<=', $em60->format('H:i:s'))
            ->orderBy('r.hora_reserva')
            ->limit(20)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        $porUnidade = (clone $base)
            ->whereDate('r.data_reserva', $hoje)
            ->select(DB::raw('COALESCE(u.nome, "Sem unidade") as rotulo'), DB::raw('COUNT(*) as qtd'), DB::raw('COALESCE(SUM(r.qtd_pessoas),0) as pessoas'))
            ->groupBy('rotulo')
            ->orderByDesc('qtd')
            ->get()
            ->map(fn ($r) => [
                'unidade' => (string) $r->rotulo,
                'quantidade' => (int) $r->qtd,
                'pessoas' => (int) $r->pessoas,
            ])
            ->all();

        $porStatus = (clone $base)
            ->select('r.status', DB::raw('COUNT(*) as qtd'))
            ->groupBy('r.status')
            ->pluck('qtd', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $porHorario = (clone $base)
            ->whereDate('r.data_reserva', $hoje)
            ->whereIn('r.status', self::STATUS_ATIVAS)
            ->select(DB::raw("DATE_FORMAT(r.hora_reserva, '%H:00') as faixa"), DB::raw('COUNT(*) as qtd'))
            ->groupBy('faixa')
            ->orderBy('faixa')
            ->get()
            ->map(fn ($r) => ['horario' => (string) $r->faixa, 'quantidade' => (int) $r->qtd])
            ->all();

        $mesasMaisUsadas = (clone $base)
            ->whereIn('r.status', array_merge(self::STATUS_ATIVAS, ['finalizada', 'cliente_chegou']))
            ->select(
                'r.mesa_id',
                DB::raw('COALESCE(m.numero_mesa, "?") as numero_mesa'),
                DB::raw('COALESCE(m.nome_mesa, "") as nome_mesa'),
                DB::raw('COUNT(*) as qtd')
            )
            ->groupBy('r.mesa_id', 'm.numero_mesa', 'm.nome_mesa')
            ->orderByDesc('qtd')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'mesa_id' => (int) $r->mesa_id,
                'mesa' => trim($r->nome_mesa) !== '' ? (string) $r->nome_mesa : ('Mesa '.$r->numero_mesa),
                'quantidade_reservas' => (int) $r->qtd,
            ])
            ->all();

        $mesasHoje = $this->ocupacaoMesasDoDia($filtros, $hoje);

        return [
            'total_reservas' => $total,
            'reservas_hoje' => $hojeN,
            'reservas_amanha' => $amanhaN,
            'pendentes' => $pendentes,
            'confirmadas' => $confirmadas,
            'concluidas' => $concluidas,
            'canceladas' => $canceladas,
            'proximas_do_horario' => $proximas,
            'total_pessoas_esperadas_hoje' => $pessoasHoje,
            'por_unidade' => $porUnidade,
            'por_status' => $porStatus,
            'por_horario' => $porHorario,
            'mesas_mais_utilizadas' => $mesasMaisUsadas,
            'ocupacao_hoje' => $mesasHoje,
        ];
    }

    /**
     * Resumo + lista de reservas de uma unidade.
     *
     * @return array<string, mixed>
     */
    public function porUnidade(int $unidadeId, ?int $userId = null): array
    {
        $unidade = Schema::hasTable('unidades')
            ? DB::table('unidades')->where('id', $unidadeId)->first(['id', 'nome'])
            : null;

        $filtros = ['unidade_id' => $unidadeId];
        $hoje = Carbon::today()->toDateString();

        return [
            'unidade' => $unidade
                ? ['id' => (int) $unidade->id, 'nome' => (string) $unidade->nome]
                : ['id' => $unidadeId, 'nome' => null],
            'resumo' => $this->resumo($filtros, $userId),
            'reservas_hoje' => $this->consultar($filtros + ['data' => $hoje, 'limite' => 50], $userId)['reservas'],
        ];
    }

    /**
     * Disponibilidade de mesas para unidade + data + horário.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function disponibilidade(array $args): array
    {
        $analise = app(\App\Services\Reservas\ReservaDisponibilidadeService::class)->analisar($args);

        // Compatibilidade com consumidores antigos (sugestao / capacidade_total_disponivel).
        if (! isset($analise['sugestao']) && is_array($analise['sugestao_operacional'] ?? null)) {
            $sug = $analise['sugestao_operacional'];
            $analise['sugestao'] = $sug['mesa'] ?? ($sug['mesas'][0] ?? $sug);
        }
        if (! isset($analise['capacidade_total_disponivel'])) {
            $analise['capacidade_total_disponivel'] = (int) ($analise['capacidade_total'] ?? 0);
        }

        return $analise;
    }

    /**
     * Alertas operacionais de reservas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function alertas(array $filtros = [], ?int $userId = null): array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return $this->alertasVazio();
        }

        $hoje = Carbon::today()->toDateString();
        $agora = Carbon::now();
        $em60 = Carbon::now()->addMinutes(60);
        $desde7 = Carbon::today()->subDays(7)->toDateString();

        $base = $this->queryReservas();
        $this->aplicarFiltrosEscopo($base, $filtros);

        $proximas = (clone $base)
            ->whereDate('r.data_reserva', $hoje)
            ->whereIn('r.status', ['pendente', 'confirmada'])
            ->whereTime('r.hora_reserva', '>=', $agora->format('H:i:s'))
            ->whereTime('r.hora_reserva', '<=', $em60->format('H:i:s'))
            ->orderBy('r.hora_reserva')
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        $semConfirmacao = (clone $base)
            ->whereDate('r.data_reserva', '>=', $hoje)
            ->where('r.status', 'pendente')
            ->orderBy('r.data_reserva')
            ->orderBy('r.hora_reserva')
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        $atrasadas = (clone $base)
            ->whereDate('r.data_reserva', $hoje)
            ->whereIn('r.status', ['pendente', 'confirmada'])
            ->whereTime('r.hora_reserva', '<', $agora->format('H:i:s'))
            ->orderBy('r.hora_reserva')
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        $canceladasRecentes = (clone $base)
            ->whereIn('r.status', ['cancelada', 'no_show'])
            ->whereDate('r.updated_at', '>=', $desde7)
            ->orderByDesc('r.updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        $semMesa = (clone $base)
            ->where(function (Builder $qq) {
                $qq->whereNull('r.mesa_id')->orWhere('r.mesa_id', 0);
            })
            ->whereIn('r.status', self::STATUS_ATIVAS)
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        $acimaCapacidade = (clone $base)
            ->whereIn('r.status', self::STATUS_ATIVAS)
            ->whereNotNull('m.capacidade')
            ->whereColumn('r.qtd_pessoas', '>', 'm.capacidade')
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->formatarReserva($r))
            ->all();

        // Conflitos: mesma mesa + data + hora com mais de uma ativa.
        $conflitos = [];
        $dup = (clone $base)
            ->whereIn('r.status', self::STATUS_ATIVAS)
            ->select('r.mesa_id', 'r.data_reserva', 'r.hora_reserva', DB::raw('COUNT(*) as qtd'), DB::raw('GROUP_CONCAT(r.id) as ids'))
            ->groupBy('r.mesa_id', 'r.data_reserva', 'r.hora_reserva')
            ->having('qtd', '>', 1)
            ->limit(30)
            ->get();

        foreach ($dup as $d) {
            $conflitos[] = [
                'mesa_id' => (int) $d->mesa_id,
                'data' => substr((string) $d->data_reserva, 0, 10),
                'hora' => substr((string) $d->hora_reserva, 0, 5),
                'quantidade' => (int) $d->qtd,
                'reserva_ids' => array_map('intval', explode(',', (string) $d->ids)),
            ];
        }

        return [
            'proximas_do_horario' => $proximas,
            'sem_confirmacao' => $semConfirmacao,
            'atrasadas' => $atrasadas,
            'canceladas_recentes' => $canceladasRecentes,
            'sem_mesa' => $semMesa,
            'acima_capacidade' => $acimaCapacidade,
            'conflitos' => $conflitos,
        ];
    }

    /** Resolve nome de unidade → ID. */
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

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function queryReservas(): Builder
    {
        return DB::table('reservas_mesas as r')
            ->leftJoin('mesas as m', 'm.id', '=', 'r.mesa_id')
            ->leftJoin('unidades as u', 'u.id', '=', 'r.unidade_id')
            ->select([
                'r.id', 'r.unidade_id', 'r.mesa_id', 'r.usuario_id',
                'r.nome_cliente', 'r.telefone_cliente',
                'r.data_reserva', 'r.hora_reserva', 'r.qtd_pessoas',
                'r.status', 'r.observacao', 'r.local', 'r.ocasiao',
                'r.created_at', 'r.updated_at',
                'm.numero_mesa', 'm.nome_mesa', 'm.capacidade', 'm.status as mesa_status',
                'u.nome as unidade_nome',
            ]);
    }

    private function aplicarRestricaoUnidades(Builder $q, string $coluna = 'r.unidade_id'): void
    {
        $permitidas = AylaSettings::unidadesPermitidas();
        if ($permitidas === []) {
            return;
        }
        $q->whereIn($coluna, $permitidas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosEscopo(Builder $q, array $filtros): void
    {
        $this->aplicarRestricaoUnidades($q);

        if (! empty($filtros['unidade_id'])) {
            $q->where('r.unidade_id', (int) $filtros['unidade_id']);
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosReserva(Builder $q, array $filtros): void
    {
        $this->aplicarFiltrosEscopo($q, $filtros);

        if (! empty($filtros['reserva_id'])) {
            $q->where('r.id', (int) $filtros['reserva_id']);
        }

        if (! empty($filtros['mesa_id'])) {
            $q->where('r.mesa_id', (int) $filtros['mesa_id']);
        }

        if (! empty($filtros['status'])) {
            $q->where('r.status', (string) $filtros['status']);
        }

        if (! empty($filtros['data'])) {
            $q->whereDate('r.data_reserva', (string) $filtros['data']);
        }

        if (! empty($filtros['data_inicio'])) {
            $q->whereDate('r.data_reserva', '>=', (string) $filtros['data_inicio']);
        }

        if (! empty($filtros['data_fim'])) {
            $q->whereDate('r.data_reserva', '<=', (string) $filtros['data_fim']);
        }

        if (! empty($filtros['cliente'])) {
            $q->where('r.nome_cliente', 'like', '%'.trim((string) $filtros['cliente']).'%');
        }

        if (! empty($filtros['telefone'])) {
            $digitos = preg_replace('/\D+/', '', (string) $filtros['telefone']) ?? '';
            if ($digitos !== '') {
                $q->where('r.telefone_cliente', 'like', '%'.$digitos.'%');
            }
        }

        if (! empty($filtros['quantidade_minima'])) {
            $q->where('r.qtd_pessoas', '>=', (int) $filtros['quantidade_minima']);
        }

        if (! empty($filtros['quantidade_maxima'])) {
            $q->where('r.qtd_pessoas', '<=', (int) $filtros['quantidade_maxima']);
        }

        if (! empty($filtros['horario_inicio'])) {
            $h = $this->normalizarHorario((string) $filtros['horario_inicio']);
            if ($h) {
                $q->whereTime('r.hora_reserva', '>=', $h);
            }
        }

        if (! empty($filtros['horario_fim'])) {
            $h = $this->normalizarHorario((string) $filtros['horario_fim']);
            if ($h) {
                $q->whereTime('r.hora_reserva', '<=', $h);
            }
        }

        if (! empty($filtros['busca'])) {
            $t = trim((string) $filtros['busca']);
            $q->where(function (Builder $qq) use ($t) {
                $qq->where('r.nome_cliente', 'like', "%{$t}%")
                    ->orWhere('r.telefone_cliente', 'like', "%{$t}%")
                    ->orWhere('r.observacao', 'like', "%{$t}%")
                    ->orWhere('r.local', 'like', "%{$t}%")
                    ->orWhere('r.ocasiao', 'like', "%{$t}%")
                    ->orWhere('m.numero_mesa', 'like', "%{$t}%")
                    ->orWhere('m.nome_mesa', 'like', "%{$t}%");
            });
        }
    }

    /**
     * Ocupação do dia (mesmas regras do resumo da API de reservas).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function ocupacaoMesasDoDia(array $filtros, string $hoje): array
    {
        if (! Schema::hasTable('mesas')) {
            return [
                'total_mesas' => 0,
                'mesas_livres' => 0,
                'mesas_reservadas' => 0,
                'mesas_ocupadas' => 0,
            ];
        }

        $mq = DB::table('mesas')->where('ativo', 1);
        $this->aplicarRestricaoUnidades($mq, 'unidade_id');
        if (! empty($filtros['unidade_id'])) {
            $mq->where('unidade_id', (int) $filtros['unidade_id']);
        }
        $totalMesas = (int) (clone $mq)->count();

        $rq = DB::table('reservas_mesas as r')
            ->whereDate('r.data_reserva', $hoje)
            ->whereIn('r.status', self::STATUS_ATIVAS);
        $this->aplicarRestricaoUnidades($rq, 'r.unidade_id');
        if (! empty($filtros['unidade_id'])) {
            $rq->where('r.unidade_id', (int) $filtros['unidade_id']);
        }

        $mesasComReserva = (int) (clone $rq)->distinct()->count('r.mesa_id');
        $ocupadas = (int) (clone $rq)->where('r.status', 'cliente_chegou')->count();
        $reservadas = (int) (clone $rq)->whereIn('r.status', ['pendente', 'confirmada'])->count();

        return [
            'total_mesas' => $totalMesas,
            'mesas_livres' => max(0, $totalMesas - $mesasComReserva),
            'mesas_reservadas' => $reservadas,
            'mesas_ocupadas' => $ocupadas,
        ];
    }

    /** @return array<string, mixed> */
    private function formatarReserva(object $row, bool $completo = false): array
    {
        $hora = $row->hora_reserva ? substr((string) $row->hora_reserva, 0, 5) : null;
        $data = $row->data_reserva ? substr((string) $row->data_reserva, 0, 10) : null;

        $out = [
            'id' => (int) $row->id,
            'unidade_id' => $row->unidade_id ? (int) $row->unidade_id : null,
            'unidade' => $row->unidade_nome ? (string) $row->unidade_nome : null,
            'mesa_id' => $row->mesa_id ? (int) $row->mesa_id : null,
            'mesa' => $row->nome_mesa
                ? (string) $row->nome_mesa
                : ($row->numero_mesa ? 'Mesa '.$row->numero_mesa : null),
            'numero_mesa' => $row->numero_mesa ? (string) $row->numero_mesa : null,
            'capacidade_mesa' => $row->capacidade !== null ? (int) $row->capacidade : null,
            'cliente' => (string) $row->nome_cliente,
            'telefone' => $row->telefone_cliente ? (string) $row->telefone_cliente : null,
            'data' => $data,
            'hora' => $hora,
            'pessoas' => (int) $row->qtd_pessoas,
            'status' => (string) $row->status,
            'local' => $row->local ? (string) $row->local : null,
            'ocasiao' => $row->ocasiao ? (string) $row->ocasiao : null,
        ];

        if ($completo) {
            $out['observacao'] = $row->observacao ? (string) $row->observacao : null;
            $out['mesa_status'] = $row->mesa_status ? (string) $row->mesa_status : null;
            $out['usuario_id'] = $row->usuario_id ? (int) $row->usuario_id : null;
            $out['criada_em'] = $row->created_at ? (string) $row->created_at : null;
            $out['atualizada_em'] = $row->updated_at ? (string) $row->updated_at : null;
            if ($row->capacidade !== null && (int) $row->qtd_pessoas > (int) $row->capacidade) {
                $out['alerta_capacidade'] = true;
            }
        }

        return $out;
    }

    private function normalizarHorario(string $h): ?string
    {
        $h = trim($h);
        if ($h === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $h, $m)) {
            $hh = (int) $m[1];
            $mm = (int) $m[2];
            $ss = isset($m[3]) ? (int) $m[3] : 0;
            if ($hh > 23 || $mm > 59 || $ss > 59) {
                return null;
            }

            return sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function resumoVazio(): array
    {
        return [
            'total_reservas' => 0,
            'reservas_hoje' => 0,
            'reservas_amanha' => 0,
            'pendentes' => 0,
            'confirmadas' => 0,
            'concluidas' => 0,
            'canceladas' => 0,
            'proximas_do_horario' => [],
            'total_pessoas_esperadas_hoje' => 0,
            'por_unidade' => [],
            'por_status' => [],
            'por_horario' => [],
            'mesas_mais_utilizadas' => [],
            'ocupacao_hoje' => [
                'total_mesas' => 0,
                'mesas_livres' => 0,
                'mesas_reservadas' => 0,
                'mesas_ocupadas' => 0,
            ],
        ];
    }

    /** @return array<string, array<mixed>> */
    private function alertasVazio(): array
    {
        return [
            'proximas_do_horario' => [],
            'sem_confirmacao' => [],
            'atrasadas' => [],
            'canceladas_recentes' => [],
            'sem_mesa' => [],
            'acima_capacidade' => [],
            'conflitos' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosAplicados(array $filtros): array
    {
        $out = [];
        foreach ([
            'busca', 'reserva_id', 'unidade_id', 'mesa_id', 'status', 'data',
            'data_inicio', 'data_fim', 'cliente', 'telefone', 'quantidade_minima',
            'quantidade_maxima', 'horario_inicio', 'horario_fim', 'limite', 'limit',
        ] as $k) {
            if (isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null) {
                // Telefone nunca vai completo para o eco de filtros se for logado depois.
                if ($k === 'telefone') {
                    $out[$k] = '[MASKED]';
                } else {
                    $out[$k] = $filtros[$k];
                }
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Escrita controlada (somente após confirmação via AylaAcaoPendenteService)
    // ------------------------------------------------------------------

    /**
     * Valida intenção e monta preview sem persistir a reserva.
     *
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function prepararPreview(string $acao, array $dados): array
    {
        $acao = strtolower(trim($acao));

        return match ($acao) {
            'criar', 'criar_reserva', 'preparar_composicao_mesas' => $this->previewCriar($dados),
            'atualizar', 'atualizar_reserva' => $this->previewAtualizar($dados),
            'alterar_mesa' => $this->previewAlterarMesa($dados),
            'confirmar', 'confirmar_reserva' => $this->previewStatus('confirmar', $dados),
            'registrar_chegada' => $this->previewStatus('registrar_chegada', $dados),
            'finalizar', 'finalizar_reserva' => $this->previewStatus('finalizar', $dados),
            'cancelar', 'cancelar_reserva' => $this->previewStatus('cancelar', $dados),
            'criar_mesa_emergencial', 'criar_mesa_emergencial_e_reservar' => $this->previewMesaEmergencial($dados, str_contains($acao, 'e_reservar')),
            'ajustar_capacidade_mesa' => $this->previewAjusteCapacidade($dados),
            'criar_alerta_operacional' => $this->previewAlertaOperacional($dados),
            default => ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Ação desconhecida.'],
        };
    }

    /**
     * Executa ação já confirmada. Reaproveita as mesmas regras do ReservaMesaController.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function executarAcaoConfirmada(string $acao, array $payload, ?int $usuarioId): array
    {
        return match ($acao) {
            'criar', 'criar_reserva', 'preparar_composicao_mesas' => $this->criarReserva($payload, $usuarioId),
            'atualizar', 'atualizar_reserva' => $this->atualizarReserva((int) ($payload['reserva_id'] ?? 0), $payload, $usuarioId),
            'alterar_mesa' => $this->alterarMesa((int) ($payload['reserva_id'] ?? 0), (int) ($payload['mesa_id'] ?? 0), $usuarioId),
            'confirmar', 'confirmar_reserva' => $this->confirmarReserva((int) ($payload['reserva_id'] ?? 0), $usuarioId),
            'registrar_chegada' => $this->registrarChegada((int) ($payload['reserva_id'] ?? 0), $usuarioId),
            'finalizar', 'finalizar_reserva' => $this->finalizarReserva((int) ($payload['reserva_id'] ?? 0), $usuarioId),
            'cancelar', 'cancelar_reserva' => $this->cancelarReserva((int) ($payload['reserva_id'] ?? 0), $payload['motivo'] ?? null, $usuarioId),
            'criar_mesa_emergencial' => $this->criarMesaEmergencial($payload['mesa'] ?? $payload, $usuarioId),
            'criar_mesa_emergencial_e_reservar' => $this->criarMesaEmergencialEReservar($payload, $usuarioId),
            'ajustar_capacidade_mesa' => $this->ajustarCapacidadeMesa($payload, $usuarioId),
            'criar_alerta_operacional' => $this->criarAlertaOperacional($payload, $usuarioId),
            default => ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Ação desconhecida.'],
        };
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function criarReserva(array $dados, ?int $usuarioId = null): array
    {
        if (! Schema::hasTable('reservas_mesas') || ! Schema::hasTable('mesas')) {
            return ['ok' => false, 'code' => 'UNAVAILABLE', 'message' => 'Tabelas de reservas indisponíveis.'];
        }

        $normalizado = $this->normalizarDadosCriacao($dados);

        // Composição explícita no payload
        $vinculos = $this->extrairVinculosMesas($normalizado);
        if ($vinculos === []) {
            $val = $this->validarCriacao($normalizado);
            if (! ($val['ok'] ?? false)) {
                return $val;
            }
            /** @var Mesa $mesa */
            $mesa = $val['mesa'];
            $payload = $val['payload'];
            $vinculos = [[
                'mesa_id' => (int) $mesa->id,
                'principal' => true,
                'capacidade_utilizada' => (int) $payload['qtd_pessoas'],
                'cadeiras_extras_utilizadas' => max(0, (int) $payload['qtd_pessoas'] - $mesa->capacidadeBase()),
                'configuracao_emergencial' => ! empty($payload['configuracao_emergencial']),
            ]];
            $normalizado = $payload;
        } else {
            $chk = $this->validarVinculosParaCriacao($normalizado, $vinculos);
            if (! ($chk['ok'] ?? false)) {
                return $chk;
            }
            $normalizado = $chk['payload'];
            $vinculos = $chk['vinculos'];
        }

        $mesaPrincipalId = (int) collect($vinculos)->firstWhere('principal', true)['mesa_id']
            ?? (int) $vinculos[0]['mesa_id'];

        try {
            $reserva = DB::transaction(function () use ($normalizado, $vinculos, $mesaPrincipalId, $usuarioId) {
                $mesaIds = array_map(fn ($v) => (int) $v['mesa_id'], $vinculos);
                $mesas = Mesa::query()->whereIn('id', $mesaIds)->lockForUpdate()->get()->keyBy('id');

                foreach ($vinculos as $v) {
                    $mesa = $mesas->get((int) $v['mesa_id']);
                    if (! $mesa || ! $mesa->ativo || $mesa->status === Mesa::STATUS_BLOQUEADA) {
                        throw new \RuntimeException('MESA_INVALIDA');
                    }
                    if ((int) $mesa->unidade_id !== (int) $normalizado['unidade_id']) {
                        throw new \RuntimeException('MESA_UNIDADE');
                    }
                    $conflito = $this->verificarConflito([
                        'mesa_id' => (int) $v['mesa_id'],
                        'data_reserva' => $normalizado['data_reserva'],
                        'hora_reserva' => $normalizado['hora_reserva'],
                    ]);
                    if ($conflito['tem_conflito']) {
                        throw new \RuntimeException('CONFLITO');
                    }
                }

                $reserva = ReservaMesa::create([
                    'unidade_id' => $normalizado['unidade_id'],
                    'mesa_id' => $mesaPrincipalId,
                    'usuario_id' => $usuarioId,
                    'nome_cliente' => $normalizado['nome_cliente'],
                    'telefone_cliente' => $normalizado['telefone_cliente'] ?? null,
                    'data_reserva' => $normalizado['data_reserva'],
                    'hora_reserva' => $normalizado['hora_reserva'],
                    'qtd_pessoas' => $normalizado['qtd_pessoas'],
                    'status' => $normalizado['status'] ?? ReservaMesa::STATUS_PENDENTE,
                    'observacao' => $normalizado['observacao'] ?? null,
                    'local' => $normalizado['local'] ?? null,
                    'ocasiao' => $normalizado['ocasiao'] ?? null,
                ]);

                $this->sincronizarVinculos($reserva, $vinculos);

                foreach ($mesaIds as $mid) {
                    Mesa::where('id', $mid)->update(['status' => Mesa::STATUS_RESERVADA]);
                }

                return $reserva->fresh(['mesa:id,numero_mesa,nome_mesa,capacidade', 'unidade:id,nome']);
            });
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'CONFLITO' => ['ok' => false, 'code' => 'CONFLICT', 'message' => 'Já existe uma reserva para esta mesa no mesmo horário.'],
                'MESA_INVALIDA' => ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa inválida, inativa ou bloqueada.'],
                'MESA_UNIDADE' => ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa não pertence à unidade selecionada.'],
                default => throw $e,
            };
        }

        $alerta = $this->criarAlertaOperacionalSeNecessario($reserva, $vinculos, $usuarioId);

        $msg = 'Reserva criada com sucesso.';
        if ($this->vinculoEhEmergencial($vinculos)) {
            $msg .= ' Atenção: organize fisicamente a mesa e as cadeiras antes do horário do cliente.';
        }

        return [
            'ok' => true,
            'data' => [
                'mensagem' => $msg,
                'reserva' => $this->serializarModelo($reserva),
                'mesas_vinculadas' => $vinculos,
                'alerta_operacional' => $alerta,
                'anterior' => null,
                'novo' => $this->serializarModelo($reserva),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function atualizarReserva(int $id, array $dados, ?int $usuarioId = null): array
    {
        if ($id < 1) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'reserva_id inválido.'];
        }

        $reserva = ReservaMesa::with(['mesa', 'unidade'])->find($id);
        if (! $reserva) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Reserva não encontrada.'];
        }
        if (! AylaSettings::unidadePermitida((int) $reserva->unidade_id)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }
        if (in_array($reserva->status, [ReservaMesa::STATUS_CANCELADA, ReservaMesa::STATUS_FINALIZADA, ReservaMesa::STATUS_NO_SHOW], true)) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Reserva não pode ser editada neste status.'];
        }

        $anterior = $this->serializarModelo($reserva);
        $campos = $this->somenteCamposEditaveis($dados);

        $mesaId = (int) ($campos['mesa_id'] ?? $reserva->mesa_id);
        $dataReserva = (string) ($campos['data_reserva'] ?? $reserva->data_reserva->format('Y-m-d'));
        $horaReserva = $this->normalizarHorarioCurto((string) ($campos['hora_reserva'] ?? substr((string) $reserva->hora_reserva, 0, 5)));
        $qtd = (int) ($campos['qtd_pessoas'] ?? $reserva->qtd_pessoas);

        if (isset($campos['data_reserva']) && $dataReserva < Carbon::today()->toDateString()) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Data não pode ser no passado.'];
        }

        $mesa = Mesa::find($mesaId);
        if (! $mesa || ! $mesa->ativo) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa inválida ou inativa.'];
        }
        if ((int) $mesa->unidade_id !== (int) $reserva->unidade_id) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa não pertence à unidade da reserva.'];
        }
        if ($qtd > (int) $mesa->capacidade) {
            return [
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => "A mesa suporta no máximo {$mesa->capacidade} pessoas.",
            ];
        }

        $mudouSlot = $mesaId !== (int) $reserva->mesa_id
            || $dataReserva !== $reserva->data_reserva->format('Y-m-d')
            || $horaReserva !== substr((string) $reserva->hora_reserva, 0, 5);

        if ($mudouSlot) {
            $conflito = $this->verificarConflito([
                'mesa_id' => $mesaId,
                'data_reserva' => $dataReserva,
                'hora_reserva' => $horaReserva,
                'exceto_id' => $id,
            ]);
            if ($conflito['tem_conflito']) {
                return [
                    'ok' => false,
                    'code' => 'CONFLICT',
                    'message' => 'Já existe uma reserva para esta mesa no mesmo horário.',
                    'data' => $conflito,
                ];
            }
        }

        DB::transaction(function () use ($reserva, $campos, $mesaId, $dataReserva, $horaReserva, $mudouSlot, $mesa) {
            if ($mudouSlot) {
                $antiga = Mesa::find($reserva->mesa_id);
                if ($antiga) {
                    $outras = ReservaMesa::where('mesa_id', $antiga->id)
                        ->where('id', '!=', $reserva->id)
                        ->whereDate('data_reserva', $reserva->data_reserva)
                        ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
                        ->exists();
                    if (! $outras) {
                        $antiga->update(['status' => Mesa::STATUS_LIVRE]);
                    }
                }
                $mesa->update(['status' => Mesa::STATUS_RESERVADA]);
            }

            $update = $campos;
            if (isset($update['mesa_id'])) {
                $update['mesa_id'] = $mesaId;
            }
            if (isset($update['data_reserva'])) {
                $update['data_reserva'] = $dataReserva;
            }
            if (isset($update['hora_reserva'])) {
                $update['hora_reserva'] = $horaReserva;
            }
            // Nunca altera unidade_id ou usuario_id por esta rota.
            unset($update['unidade_id'], $update['usuario_id'], $update['reserva_id'], $update['motivo'], $update['forcar_duplicidade']);

            $reserva->update($update);
        });

        $fresca = $reserva->fresh(['mesa:id,numero_mesa,nome_mesa,capacidade', 'unidade:id,nome']);

        return [
            'ok' => true,
            'data' => [
                'mensagem' => 'Reserva atualizada.',
                'reserva' => $this->serializarModelo($fresca),
                'anterior' => $anterior,
                'novo' => $this->serializarModelo($fresca),
            ],
        ];
    }

    /** @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>} */
    public function confirmarReserva(int $id, ?int $usuarioId = null): array
    {
        return $this->alterarStatusReserva($id, ReservaMesa::STATUS_CONFIRMADA, $usuarioId);
    }

    /** @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>} */
    public function registrarChegada(int $id, ?int $usuarioId = null): array
    {
        return $this->alterarStatusReserva($id, ReservaMesa::STATUS_CLIENTE_CHEGOU, $usuarioId);
    }

    /** @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>} */
    public function finalizarReserva(int $id, ?int $usuarioId = null): array
    {
        return $this->alterarStatusReserva($id, ReservaMesa::STATUS_FINALIZADA, $usuarioId);
    }

    /**
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function cancelarReserva(int $id, ?string $motivo = null, ?int $usuarioId = null): array
    {
        $r = $this->alterarStatusReserva($id, ReservaMesa::STATUS_CANCELADA, $usuarioId);
        if (($r['ok'] ?? false) && $motivo !== null && trim($motivo) !== '') {
            $reserva = ReservaMesa::find($id);
            if ($reserva) {
                $obs = trim((string) ($reserva->observacao ?? ''));
                $nota = 'Cancelamento (Ayla): '.mb_substr(trim($motivo), 0, 400);
                $reserva->update([
                    'observacao' => $obs !== '' ? ($obs."\n".$nota) : $nota,
                ]);
                $r['data']['reserva'] = $this->serializarModelo($reserva->fresh(['mesa', 'unidade']));
                $r['data']['motivo'] = mb_substr(trim($motivo), 0, 400);
            }
        }

        return $r;
    }

    /**
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function alterarMesa(int $id, int $mesaId, ?int $usuarioId = null): array
    {
        return $this->atualizarReserva($id, ['mesa_id' => $mesaId], $usuarioId);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{tem_conflito: bool, conflitos: array<int, array<string, mixed>>}
     */
    public function verificarConflito(array $dados): array
    {
        $mesaId = (int) ($dados['mesa_id'] ?? 0);
        $data = (string) ($dados['data_reserva'] ?? $dados['data'] ?? '');
        $hora = $this->normalizarHorarioCurto((string) ($dados['hora_reserva'] ?? $dados['horario'] ?? ''));
        $exceto = isset($dados['exceto_id']) ? (int) $dados['exceto_id'] : null;

        if ($mesaId < 1 || $data === '' || $hora === null) {
            return ['tem_conflito' => false, 'conflitos' => []];
        }

        $q = ReservaMesa::query()
            ->where('mesa_id', $mesaId)
            ->whereDate('data_reserva', $data)
            ->whereTime('hora_reserva', $hora)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);

        if ($exceto) {
            $q->where('id', '!=', $exceto);
        }

        $rows = $q->get(['id', 'nome_cliente', 'status', 'qtd_pessoas', 'unidade_id']);

        // Conflito também via pivô de composição (mesas secundárias).
        if (Schema::hasTable('reserva_mesas')) {
            $reservaIds = DB::table('reserva_mesas')->where('mesa_id', $mesaId)->pluck('reserva_id');
            if ($reservaIds->isNotEmpty()) {
                $q2 = ReservaMesa::query()
                    ->whereIn('id', $reservaIds->all())
                    ->whereDate('data_reserva', $data)
                    ->whereTime('hora_reserva', $hora)
                    ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);
                if ($exceto) {
                    $q2->where('id', '!=', $exceto);
                }
                if ($rows->isNotEmpty()) {
                    $q2->whereNotIn('id', $rows->pluck('id')->all());
                }
                $extra = $q2->get(['id', 'nome_cliente', 'status', 'qtd_pessoas', 'unidade_id']);
                $rows = $rows->concat($extra);
            }
        }

        return [
            'tem_conflito' => $rows->isNotEmpty(),
            'conflitos' => $rows->map(fn ($r) => [
                'reserva_id' => (int) $r->id,
                'cliente' => (string) $r->nome_cliente,
                'status' => (string) $r->status,
                'qtd_pessoas' => (int) $r->qtd_pessoas,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function validarDisponibilidade(array $dados): array
    {
        $disp = $this->disponibilidade([
            'unidade_id' => (int) ($dados['unidade_id'] ?? 0),
            'data' => (string) ($dados['data_reserva'] ?? $dados['data'] ?? ''),
            'horario' => (string) ($dados['hora_reserva'] ?? $dados['horario'] ?? ''),
            'quantidade_pessoas' => isset($dados['qtd_pessoas']) ? (int) $dados['qtd_pessoas'] : null,
        ]);

        $ok = ! empty($disp['sugestao_operacional'])
            && empty($disp['exige_cadastro_emergencial'])
            && empty($disp['exige_ajuste_capacidade']);
        if (! $ok) {
            $ok = ! empty($disp['mesas_disponiveis']) && empty($disp['exige_cadastro_emergencial']);
        }

        return [
            'ok' => $ok,
            'code' => $ok ? null : 'NO_AVAILABILITY',
            'message' => $ok ? 'Há mesa disponível.' : 'Nenhuma mesa disponível para os critérios.',
            'data' => $disp,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>, mesa?: Mesa, payload?: array<string, mixed>}
     */
    private function previewCriar(array $dados): array
    {
        $normalizado = $this->normalizarDadosCriacao($dados);

        // Composição explícita
        if (! empty($normalizado['mesas']) && is_array($normalizado['mesas'])) {
            $chk = $this->validarVinculosParaCriacao($normalizado, $normalizado['mesas']);
            if (! ($chk['ok'] ?? false)) {
                return $chk;
            }
            $payload = $chk['payload'];
            $vinculos = $chk['vinculos'];
            $texto = $this->textoResumoComposicao($payload, $vinculos);

            return [
                'ok' => true,
                'data' => [
                    'payload' => array_merge($payload, ['mesas' => $vinculos]),
                    'resumo' => [
                        'cliente' => $payload['nome_cliente'],
                        'data' => $payload['data_reserva'],
                        'horario' => $payload['hora_reserva'],
                        'pessoas' => $payload['qtd_pessoas'],
                        'unidade_id' => $payload['unidade_id'],
                        'mesas' => $vinculos,
                        'composicao' => true,
                    ],
                    'resumo_texto' => $texto,
                    'possivel_duplicidade' => $this->buscarSimilares($payload) !== [],
                    'similares' => $this->buscarSimilares($payload),
                ],
            ];
        }

        $analise = app(\App\Services\Reservas\ReservaDisponibilidadeService::class)->analisar([
            'unidade_id' => (int) ($normalizado['unidade_id'] ?? 0),
            'data' => (string) ($normalizado['data_reserva'] ?? ''),
            'horario' => (string) ($normalizado['hora_reserva'] ?? ''),
            'quantidade_pessoas' => (int) ($normalizado['qtd_pessoas'] ?? 1),
            'duracao_minutos' => $normalizado['duracao_minutos'] ?? null,
        ]);

        $sug = $analise['sugestao_operacional'] ?? null;

        if (! empty($analise['exige_cadastro_emergencial'])) {
            return [
                'ok' => false,
                'code' => 'NO_AVAILABILITY',
                'message' => $analise['sugestao_operacional']['mensagem'] ?? 'Sem capacidade suficiente.',
                'data' => [
                    'exige_cadastro_emergencial' => true,
                    'exige_ajuste_capacidade' => (bool) ($analise['exige_ajuste_capacidade'] ?? false),
                    'sugestao_operacional' => $analise['sugestao_operacional'] ?? null,
                    'opcoes' => $analise['sugestao_operacional']['opcoes'] ?? [],
                    'alternativas' => $analise['alternativas'] ?? [],
                ],
            ];
        }

        if (! empty($analise['exige_ajuste_capacidade'])) {
            return [
                'ok' => false,
                'code' => 'NEEDS_CAPACITY_ADJUSTMENT',
                'message' => $analise['sugestao_operacional']['mensagem'] ?? 'É necessário ajustar capacidade.',
                'data' => [
                    'exige_ajuste_capacidade' => true,
                    'sugestao_operacional' => $analise['sugestao_operacional'] ?? null,
                ],
            ];
        }

        if (! is_array($sug)) {
            $val = $this->validarCriacao($normalizado, true);
            if (! ($val['ok'] ?? false)) {
                return $val;
            }
            $mesa = $val['mesa'];
            $payload = $val['payload'];
            $extras = max(0, (int) $payload['qtd_pessoas'] - $mesa->capacidadeBase());
            $vinculos = [[
                'mesa_id' => (int) $mesa->id,
                'principal' => true,
                'capacidade_utilizada' => (int) $payload['qtd_pessoas'],
                'cadeiras_extras_utilizadas' => $extras,
                'configuracao_emergencial' => $extras > 0,
            ]];
        } elseif (($sug['tipo'] ?? '') === 'composicao') {
            $vinculos = $sug['mesas'] ?? [];
            $mesaIdPrincipal = (int) (collect($vinculos)->firstWhere('principal', true)['mesa_id'] ?? ($vinculos[0]['mesa_id'] ?? 0));
            $normalizado['mesa_id'] = $mesaIdPrincipal;
            $val = $this->validarCriacao($normalizado, false);
            if (! ($val['ok'] ?? false) && ($val['code'] ?? '') !== 'CONFLICT') {
                // validarCriacao may fail on single mesa capacity - OK for composition
                if (empty($normalizado['nome_cliente']) || empty($normalizado['unidade_id'])) {
                    return $val;
                }
                $payload = array_merge($this->payloadBaseDeNormalizado($normalizado), [
                    'mesa_id' => $mesaIdPrincipal,
                ]);
            } else {
                $payload = $val['payload'] ?? $this->payloadBaseDeNormalizado($normalizado);
                $payload['mesa_id'] = $mesaIdPrincipal;
            }
            $texto = $sug['mensagem'] ?? $this->textoResumoComposicao($payload, $vinculos);

            return [
                'ok' => true,
                'data' => [
                    'payload' => array_merge($payload, ['mesas' => $vinculos, 'configuracao_emergencial' => true]),
                    'resumo' => [
                        'cliente' => $payload['nome_cliente'],
                        'data' => $payload['data_reserva'],
                        'horario' => $payload['hora_reserva'],
                        'pessoas' => $payload['qtd_pessoas'],
                        'unidade_id' => $payload['unidade_id'],
                        'mesas' => $vinculos,
                        'composicao' => true,
                        'capacidade_total' => $sug['capacidade_total'] ?? null,
                    ],
                    'resumo_texto' => $texto."\n\nDeseja confirmar a composição?",
                    'possivel_duplicidade' => $this->buscarSimilares($payload) !== [],
                    'similares' => $this->buscarSimilares($payload),
                    'alternativas' => $analise['composicoes'] ?? [],
                ],
            ];
        } else {
            $mesaId = (int) ($sug['mesa_id'] ?? $sug['mesa']['mesa_id'] ?? 0);
            $extras = (int) ($sug['cadeiras_extras_utilizadas'] ?? 0);
            $normalizado['mesa_id'] = $mesaId;
            $val = $this->validarCriacao($normalizado, true);
            if (! ($val['ok'] ?? false)) {
                return $val;
            }
            $mesa = $val['mesa'];
            $payload = $val['payload'];
            $vinculos = [[
                'mesa_id' => (int) $mesa->id,
                'principal' => true,
                'capacidade_utilizada' => (int) $payload['qtd_pessoas'],
                'cadeiras_extras_utilizadas' => $extras,
                'configuracao_emergencial' => $extras > 0,
            ]];
        }

        /** @var Mesa $mesa */
        $mesa = $mesa ?? Mesa::find((int) ($vinculos[0]['mesa_id'] ?? 0));
        $unidadeNome = Schema::hasTable('unidades')
            ? (string) (DB::table('unidades')->where('id', $payload['unidade_id'])->value('nome') ?? '')
            : '';

        $extras = (int) ($vinculos[0]['cadeiras_extras_utilizadas'] ?? 0);
        $resumo = [
            'cliente' => $payload['nome_cliente'],
            'telefone' => $this->mascararTelefone($payload['telefone_cliente'] ?? null),
            'data' => $payload['data_reserva'],
            'horario' => $payload['hora_reserva'],
            'pessoas' => $payload['qtd_pessoas'],
            'unidade_id' => $payload['unidade_id'],
            'unidade' => $unidadeNome,
            'mesa_id' => (int) $mesa->id,
            'mesa' => $mesa->nome_mesa ?: ('Mesa '.$mesa->numero_mesa),
            'capacidade_base' => $mesa->capacidadeBase(),
            'cadeiras_extras' => $extras,
            'capacidade_maxima' => $mesa->capacidadeMaximaCalculada(),
            'status' => $payload['status'] ?? ReservaMesa::STATUS_PENDENTE,
            'observacao' => $payload['observacao'] ?? null,
        ];

        $texto = $extras > 0
            ? sprintf(
                "Encontrei a Mesa %s.\n\nCapacidade atual: %d pessoas.\nSerá necessário acrescentar %d cadeira(s).\nCapacidade máxima: %d pessoas.\n\nDeseja confirmar essa configuração?",
                $resumo['mesa'],
                $resumo['capacidade_base'],
                $extras,
                $resumo['capacidade_maxima']
            )
            : sprintf(
                "Reserva:\n- Cliente: %s\n- Data: %s\n- Horário: %s\n- Pessoas: %d\n- Unidade: %s\n- Mesa sugerida: %s\n\nDeseja confirmar a criação da reserva?",
                $resumo['cliente'],
                Carbon::parse($resumo['data'])->format('d/m/Y'),
                $resumo['horario'],
                $resumo['pessoas'],
                $resumo['unidade'] !== '' ? $resumo['unidade'] : ('Unidade '.$resumo['unidade_id']),
                $resumo['mesa']
            );

        return [
            'ok' => true,
            'data' => [
                'payload' => array_merge($payload, ['mesas' => $vinculos]),
                'resumo' => $resumo,
                'resumo_texto' => $texto,
                'possivel_duplicidade' => $this->buscarSimilares($payload) !== [],
                'similares' => $this->buscarSimilares($payload),
                'alternativas' => $analise['alternativas'] ?? ($val['alternativas'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function previewAtualizar(array $dados): array
    {
        $id = (int) ($dados['reserva_id'] ?? $dados['id'] ?? 0);
        if ($id < 1) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Informe reserva_id.'];
        }
        $reserva = ReservaMesa::with(['mesa', 'unidade'])->find($id);
        if (! $reserva) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Reserva não encontrada.'];
        }
        if (! AylaSettings::unidadePermitida((int) $reserva->unidade_id)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }
        if (in_array($reserva->status, [ReservaMesa::STATUS_CANCELADA, ReservaMesa::STATUS_FINALIZADA, ReservaMesa::STATUS_NO_SHOW], true)) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Reserva não pode ser editada neste status.'];
        }

        $campos = $this->somenteCamposEditaveis($dados);
        if ($campos === []) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Nenhum campo para atualizar.'];
        }

        $payload = array_merge($campos, ['reserva_id' => $id]);
        $anterior = $this->serializarModelo($reserva);

        $texto = "Editar reserva #{$id}:\n".json_encode($campos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nDeseja confirmar a alteração?";

        return [
            'ok' => true,
            'data' => [
                'payload' => $payload,
                'resumo' => ['reserva_id' => $id, 'alteracoes' => $campos, 'anterior' => $anterior],
                'resumo_texto' => $texto,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function previewAlterarMesa(array $dados): array
    {
        $id = (int) ($dados['reserva_id'] ?? $dados['id'] ?? 0);
        $mesaId = (int) ($dados['mesa_id'] ?? 0);
        if ($id < 1 || $mesaId < 1) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Informe reserva_id e mesa_id.'];
        }

        return $this->previewAtualizar(['reserva_id' => $id, 'mesa_id' => $mesaId]);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function previewStatus(string $acao, array $dados): array
    {
        $id = (int) ($dados['reserva_id'] ?? $dados['id'] ?? 0);
        if ($id < 1) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Informe reserva_id.'];
        }
        $reserva = ReservaMesa::with(['mesa', 'unidade'])->find($id);
        if (! $reserva) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Reserva não encontrada.'];
        }
        if (! AylaSettings::unidadePermitida((int) $reserva->unidade_id)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }

        $statusAlvo = match ($acao) {
            'confirmar' => ReservaMesa::STATUS_CONFIRMADA,
            'registrar_chegada' => ReservaMesa::STATUS_CLIENTE_CHEGOU,
            'finalizar' => ReservaMesa::STATUS_FINALIZADA,
            'cancelar' => ReservaMesa::STATUS_CANCELADA,
            default => null,
        };

        $payload = ['reserva_id' => $id];
        if ($acao === 'cancelar' && ! empty($dados['motivo'])) {
            $payload['motivo'] = mb_substr(trim((string) $dados['motivo']), 0, 400);
        }

        $labels = [
            'confirmar' => 'confirmar',
            'registrar_chegada' => 'registrar a chegada do cliente em',
            'finalizar' => 'finalizar',
            'cancelar' => 'cancelar',
        ];

        $texto = sprintf(
            "Ação: %s a reserva #%d\nCliente: %s\nStatus atual: %s → %s\n\nDeseja confirmar?",
            $labels[$acao] ?? $acao,
            $id,
            $reserva->nome_cliente,
            $reserva->status,
            $statusAlvo
        );

        return [
            'ok' => true,
            'data' => [
                'payload' => $payload,
                'resumo' => [
                    'reserva_id' => $id,
                    'acao' => $acao,
                    'status_atual' => $reserva->status,
                    'status_novo' => $statusAlvo,
                    'cliente' => $reserva->nome_cliente,
                    'unidade_id' => (int) $reserva->unidade_id,
                ],
                'resumo_texto' => $texto,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>, mesa?: Mesa, payload?: array<string, mixed>, alternativas?: mixed}
     */
    private function validarCriacao(array $dados, bool $sugerirMesa = false): array
    {
        $validator = Validator::make($dados, [
            'unidade_id' => 'required|integer|min:1',
            'mesa_id' => 'nullable|integer|min:1',
            'nome_cliente' => 'required|string|max:255',
            'telefone_cliente' => 'nullable|string|max:30',
            'data_reserva' => 'required|date|after_or_equal:today',
            'hora_reserva' => 'required|date_format:H:i',
            'qtd_pessoas' => 'required|integer|min:1|max:99',
            'status' => 'nullable|in:pendente,confirmada',
            'observacao' => 'nullable|string|max:500',
            'local' => 'nullable|string|max:100',
            'ocasiao' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return [
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Dados inválidos para criar reserva.',
                'data' => ['errors' => $validator->errors()->toArray()],
            ];
        }

        $unidadeId = (int) $dados['unidade_id'];
        if (! AylaSettings::unidadePermitida($unidadeId)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }
        if (! Schema::hasTable('unidades') || ! DB::table('unidades')->where('id', $unidadeId)->exists()) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Unidade inválida.'];
        }

        $mesa = null;
        $mesaId = isset($dados['mesa_id']) ? (int) $dados['mesa_id'] : 0;

        if ($mesaId > 0) {
            $mesa = Mesa::where('id', $mesaId)->where('ativo', 1)->first();
            if (! $mesa) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa inválida ou inativa.'];
            }
            if ((int) $mesa->unidade_id !== $unidadeId) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa não pertence à unidade selecionada.'];
            }
            if ($mesa->status === Mesa::STATUS_BLOQUEADA) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa está bloqueada para reservas.'];
            }
            $qtd = (int) $dados['qtd_pessoas'];
            $max = $mesa->capacidadeMaximaCalculada();
            if ($qtd > $max) {
                return [
                    'ok' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => "A mesa suporta no máximo {$max} pessoas (base {$mesa->capacidadeBase()} + extras).",
                ];
            }
            if ($qtd > $mesa->capacidadeBase() && ! $mesa->permiteAdicionarCadeiras()) {
                return [
                    'ok' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => "A mesa suporta no máximo {$mesa->capacidadeBase()} pessoas.",
                ];
            }
            $conflito = $this->verificarConflito([
                'mesa_id' => $mesaId,
                'data_reserva' => $dados['data_reserva'],
                'hora_reserva' => $dados['hora_reserva'],
            ]);
            if ($conflito['tem_conflito']) {
                $alt = $this->validarDisponibilidade($dados);
                return [
                    'ok' => false,
                    'code' => 'CONFLICT',
                    'message' => 'Já existe uma reserva para esta mesa no mesmo horário.',
                    'data' => [
                        'conflitos' => $conflito['conflitos'],
                        'alternativas' => $alt['data']['mesas_disponiveis'] ?? $alt['data']['composicoes'] ?? [],
                    ],
                ];
            }
        } else {
            $disp = $this->validarDisponibilidade($dados);
            $livres = $disp['data']['mesas_disponiveis'] ?? [];
            if ($livres === []) {
                return [
                    'ok' => false,
                    'code' => 'NO_AVAILABILITY',
                    'message' => 'Nenhuma mesa disponível. Sugira outro horário ou quantidade.',
                    'data' => ['ocupadas' => $disp['data']['mesas_ocupadas'] ?? []],
                ];
            }
            $sugerida = $disp['data']['sugestao'] ?? $livres[0];
            $mesa = Mesa::find((int) ($sugerida['mesa_id'] ?? 0));
            if (! $mesa) {
                return ['ok' => false, 'code' => 'NO_AVAILABILITY', 'message' => 'Não foi possível sugerir mesa.'];
            }
            $dados['mesa_id'] = (int) $mesa->id;
        }

        $payload = [
            'unidade_id' => $unidadeId,
            'mesa_id' => (int) $mesa->id,
            'nome_cliente' => trim((string) $dados['nome_cliente']),
            'telefone_cliente' => isset($dados['telefone_cliente']) ? trim((string) $dados['telefone_cliente']) : null,
            'data_reserva' => (string) $dados['data_reserva'],
            'hora_reserva' => $this->normalizarHorarioCurto((string) $dados['hora_reserva']),
            'qtd_pessoas' => (int) $dados['qtd_pessoas'],
            'status' => $dados['status'] ?? ReservaMesa::STATUS_PENDENTE,
            'observacao' => isset($dados['observacao']) ? mb_substr(trim((string) $dados['observacao']), 0, 500) : null,
            'local' => isset($dados['local']) ? mb_substr(trim((string) $dados['local']), 0, 100) : null,
            'ocasiao' => isset($dados['ocasiao']) ? mb_substr(trim((string) $dados['ocasiao']), 0, 255) : null,
            'forcar_duplicidade' => ! empty($dados['forcar_duplicidade']),
        ];

        return [
            'ok' => true,
            'mesa' => $mesa,
            'payload' => $payload,
            'alternativas' => $sugerirMesa ? ($this->validarDisponibilidade($dados)['data']['mesas_disponiveis'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalizarDadosCriacao(array $dados): array
    {
        if (isset($dados['data']) && empty($dados['data_reserva'])) {
            $dados['data_reserva'] = $dados['data'];
        }
        if (isset($dados['horario']) && empty($dados['hora_reserva'])) {
            $dados['hora_reserva'] = $dados['horario'];
        }
        if (isset($dados['quantidade_pessoas']) && empty($dados['qtd_pessoas'])) {
            $dados['qtd_pessoas'] = $dados['quantidade_pessoas'];
        }
        if (isset($dados['cliente']) && empty($dados['nome_cliente'])) {
            $dados['nome_cliente'] = $dados['cliente'];
        }
        if (isset($dados['telefone']) && empty($dados['telefone_cliente'])) {
            $dados['telefone_cliente'] = $dados['telefone'];
        }
        if (isset($dados['hora_reserva'])) {
            $h = $this->normalizarHorarioCurto((string) $dados['hora_reserva']);
            if ($h) {
                $dados['hora_reserva'] = $h;
            }
        }

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function somenteCamposEditaveis(array $dados): array
    {
        $dados = $this->normalizarDadosCriacao($dados);
        $out = [];
        foreach (['mesa_id', 'nome_cliente', 'telefone_cliente', 'data_reserva', 'hora_reserva', 'qtd_pessoas', 'observacao', 'local', 'ocasiao'] as $k) {
            if (array_key_exists($k, $dados) && $dados[$k] !== null && $dados[$k] !== '') {
                $out[$k] = $dados[$k];
            }
        }
        if (isset($out['hora_reserva'])) {
            $out['hora_reserva'] = $this->normalizarHorarioCurto((string) $out['hora_reserva']);
        }
        if (isset($out['observacao'])) {
            $out['observacao'] = mb_substr(trim((string) $out['observacao']), 0, 500);
        }

        return $out;
    }

    /**
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function alterarStatusReserva(int $id, string $statusNovo, ?int $usuarioId = null): array
    {
        if ($id < 1) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'reserva_id inválido.'];
        }
        if (! in_array($statusNovo, self::STATUS_RESERVA, true)) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Status inválido.'];
        }

        $reserva = ReservaMesa::with(['mesa', 'unidade'])->find($id);
        if (! $reserva) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Reserva não encontrada.'];
        }
        if (! AylaSettings::unidadePermitida((int) $reserva->unidade_id)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }

        $anterior = $this->serializarModelo($reserva);
        $statusAnterior = (string) $reserva->status;

        DB::transaction(function () use ($reserva, $statusNovo) {
            $reserva->update(['status' => $statusNovo]);
            $mesa = $reserva->mesa;
            if (! $mesa) {
                return;
            }
            $outras = ReservaMesa::where('mesa_id', $mesa->id)
                ->whereDate('data_reserva', $reserva->data_reserva)
                ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
                ->where('id', '!=', $reserva->id)
                ->exists();

            // Mesma lógica do ReservaMesaController::alterarStatus
            if (in_array($statusNovo, ['cancelada', 'no_show', 'finalizada'], true) && ! $outras) {
                $mesa->update(['status' => Mesa::STATUS_LIVRE]);
            } elseif ($statusNovo === 'cliente_chegou') {
                $mesa->update(['status' => Mesa::STATUS_AGUARDANDO_CLIENTE]);
            } elseif (in_array($statusNovo, ['pendente', 'confirmada'], true)) {
                $mesa->update(['status' => Mesa::STATUS_RESERVADA]);
            }
        });

        $fresca = $reserva->fresh(['mesa:id,numero_mesa,nome_mesa,capacidade', 'unidade:id,nome']);

        return [
            'ok' => true,
            'data' => [
                'mensagem' => 'Status alterado.',
                'reserva' => $this->serializarModelo($fresca),
                'anterior' => $anterior,
                'novo' => $this->serializarModelo($fresca),
                'status_anterior' => $statusAnterior,
                'status_novo' => $statusNovo,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buscarSimilares(array $payload): array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return [];
        }

        $q = ReservaMesa::query()
            ->where('unidade_id', (int) $payload['unidade_id'])
            ->whereDate('data_reserva', (string) $payload['data_reserva'])
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);

        $nome = trim((string) $payload['nome_cliente']);
        $q->where(function ($qq) use ($nome, $payload) {
            $qq->where('nome_cliente', 'like', $nome);
            $tel = preg_replace('/\D+/', '', (string) ($payload['telefone_cliente'] ?? '')) ?? '';
            if (strlen($tel) >= 8) {
                $qq->orWhere('telefone_cliente', 'like', '%'.$tel.'%');
            }
        });

        $hora = $this->normalizarHorarioCurto((string) $payload['hora_reserva']);
        // Horário próximo: ±60 minutos no mesmo dia.
        $rows = $q->limit(10)->get();

        return $rows->filter(function ($r) use ($hora, $payload) {
            $h = substr((string) $r->hora_reserva, 0, 5);
            $diff = abs($this->horaParaMinutos($h) - $this->horaParaMinutos((string) $hora));
            $qtdOk = abs((int) $r->qtd_pessoas - (int) $payload['qtd_pessoas']) <= 2;

            return $diff <= 60 && $qtdOk;
        })->map(fn ($r) => [
            'reserva_id' => (int) $r->id,
            'cliente' => (string) $r->nome_cliente,
            'horario' => substr((string) $r->hora_reserva, 0, 5),
            'pessoas' => (int) $r->qtd_pessoas,
            'status' => (string) $r->status,
        ])->values()->all();
    }

    private function horaParaMinutos(string $hora): int
    {
        $p = explode(':', $hora);

        return ((int) ($p[0] ?? 0)) * 60 + ((int) ($p[1] ?? 0));
    }

    private function normalizarHorarioCurto(?string $hora): ?string
    {
        if ($hora === null || trim($hora) === '') {
            return null;
        }
        $full = $this->normalizarHorario(trim($hora));
        if ($full === null) {
            return null;
        }

        return substr($full, 0, 5);
    }

    private function mascararTelefone(?string $tel): ?string
    {
        if ($tel === null || trim($tel) === '') {
            return null;
        }
        $d = preg_replace('/\D+/', '', $tel) ?? '';
        if (strlen($d) < 4) {
            return '[MASKED]';
        }

        return str_repeat('*', max(0, strlen($d) - 4)).substr($d, -4);
    }

    /** @return array<string, mixed> */
    private function serializarModelo(ReservaMesa $reserva): array
    {
        $reserva->loadMissing(['mesa:id,numero_mesa,nome_mesa,capacidade,capacidade_base,capacidade_maxima', 'unidade:id,nome']);

        $vinculos = [];
        if (Schema::hasTable('reserva_mesas')) {
            $vinculos = DB::table('reserva_mesas as rm')
                ->leftJoin('mesas as m', 'm.id', '=', 'rm.mesa_id')
                ->where('rm.reserva_id', $reserva->id)
                ->get([
                    'rm.mesa_id', 'rm.capacidade_utilizada', 'rm.cadeiras_extras_utilizadas',
                    'rm.principal', 'rm.configuracao_emergencial',
                    'm.numero_mesa', 'm.nome_mesa', 'm.capacidade', 'm.capacidade_base', 'm.capacidade_maxima',
                ])
                ->map(fn ($r) => [
                    'mesa_id' => (int) $r->mesa_id,
                    'numero_mesa' => $r->numero_mesa ? (string) $r->numero_mesa : null,
                    'nome' => $r->nome_mesa ? (string) $r->nome_mesa : ($r->numero_mesa ? 'Mesa '.$r->numero_mesa : null),
                    'capacidade_utilizada' => (int) $r->capacidade_utilizada,
                    'cadeiras_extras_utilizadas' => (int) $r->cadeiras_extras_utilizadas,
                    'principal' => (bool) $r->principal,
                    'configuracao_emergencial' => (bool) $r->configuracao_emergencial,
                    'capacidade_base' => (int) ($r->capacidade_base ?? $r->capacidade ?? 0),
                    'capacidade_maxima' => (int) ($r->capacidade_maxima ?? $r->capacidade ?? 0),
                ])->values()->all();
        }

        if ($vinculos === [] && $reserva->mesa) {
            $vinculos = [[
                'mesa_id' => (int) $reserva->mesa_id,
                'numero_mesa' => (string) $reserva->mesa->numero_mesa,
                'nome' => $reserva->mesa->nome_mesa ?: ('Mesa '.$reserva->mesa->numero_mesa),
                'capacidade_utilizada' => (int) $reserva->qtd_pessoas,
                'cadeiras_extras_utilizadas' => 0,
                'principal' => true,
                'configuracao_emergencial' => false,
                'capacidade_base' => $reserva->mesa->capacidadeBase(),
                'capacidade_maxima' => $reserva->mesa->capacidadeMaximaCalculada(),
            ]];
        }

        $extras = array_sum(array_column($vinculos, 'cadeiras_extras_utilizadas'));
        $emergencial = (bool) collect($vinculos)->contains(fn ($v) => ! empty($v['configuracao_emergencial']));

        return [
            'id' => (int) $reserva->id,
            'unidade_id' => (int) $reserva->unidade_id,
            'unidade' => $reserva->unidade->nome ?? null,
            'mesa_id' => (int) $reserva->mesa_id,
            'mesa' => $reserva->mesa
                ? ($reserva->mesa->nome_mesa ?: ('Mesa '.$reserva->mesa->numero_mesa))
                : null,
            'mesa_principal' => collect($vinculos)->firstWhere('principal', true) ?: ($vinculos[0] ?? null),
            'mesas_vinculadas' => $vinculos,
            'composicao' => count($vinculos) > 1,
            'cadeiras_extras' => $extras,
            'capacidade_total' => array_sum(array_column($vinculos, 'capacidade_utilizada')) ?: (int) $reserva->qtd_pessoas,
            'configuracao_emergencial' => $emergencial,
            'numero_mesa' => $reserva->mesa->numero_mesa ?? null,
            'nome_cliente' => (string) $reserva->nome_cliente,
            'telefone_cliente' => $this->mascararTelefone($reserva->telefone_cliente),
            'data_reserva' => $reserva->data_reserva?->format('Y-m-d'),
            'hora_reserva' => substr((string) $reserva->hora_reserva, 0, 5),
            'qtd_pessoas' => (int) $reserva->qtd_pessoas,
            'status' => (string) $reserva->status,
            'observacao' => $reserva->observacao ? (string) $reserva->observacao : null,
            'local' => $reserva->local ? (string) $reserva->local : null,
            'ocasiao' => $reserva->ocasiao ? (string) $reserva->ocasiao : null,
            'alerta_preparacao_fisica' => $emergencial || $extras > 0 || count($vinculos) > 1
                ? 'Organize fisicamente a mesa e as cadeiras antes do horário do cliente.'
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<int, array<string, mixed>>
     */
    private function extrairVinculosMesas(array $dados): array
    {
        if (empty($dados['mesas']) || ! is_array($dados['mesas'])) {
            return [];
        }
        $out = [];
        foreach ($dados['mesas'] as $i => $m) {
            if (! is_array($m)) {
                continue;
            }
            $mesaId = (int) ($m['mesa_id'] ?? 0);
            if ($mesaId < 1) {
                continue;
            }
            $out[] = [
                'mesa_id' => $mesaId,
                'principal' => ! empty($m['principal']) || $i === 0,
                'capacidade_utilizada' => (int) ($m['capacidade_utilizada'] ?? 0),
                'cadeiras_extras_utilizadas' => (int) ($m['cadeiras_extras_utilizadas'] ?? 0),
                'configuracao_emergencial' => ! empty($m['configuracao_emergencial']) || ! empty($dados['configuracao_emergencial']),
            ];
        }
        if ($out !== []) {
            $temPrincipal = collect($out)->contains(fn ($v) => ! empty($v['principal']));
            if (! $temPrincipal) {
                $out[0]['principal'] = true;
            }
            // garantir só um principal
            $seen = false;
            foreach ($out as &$v) {
                if (! empty($v['principal'])) {
                    if ($seen) {
                        $v['principal'] = false;
                    }
                    $seen = true;
                }
            }
            unset($v);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  array<int, array<string, mixed>>  $vinculos
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>, payload?: array<string, mixed>, vinculos?: array<int, array<string, mixed>>}
     */
    private function validarVinculosParaCriacao(array $dados, array $vinculos): array
    {
        if (count($vinculos) > 4) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Não é permitido combinar mais de 4 mesas automaticamente.'];
        }

        $validator = Validator::make($dados, [
            'unidade_id' => 'required|integer|min:1',
            'nome_cliente' => 'required|string|max:255',
            'telefone_cliente' => 'nullable|string|max:30',
            'data_reserva' => 'required|date|after_or_equal:today',
            'hora_reserva' => 'required|date_format:H:i',
            'qtd_pessoas' => 'required|integer|min:1|max:99',
            'status' => 'nullable|in:pendente,confirmada',
            'observacao' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return [
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Dados inválidos para composição.',
                'data' => ['errors' => $validator->errors()->toArray()],
            ];
        }

        $unidadeId = (int) $dados['unidade_id'];
        if (! AylaSettings::unidadePermitida($unidadeId)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }

        $capTotal = 0;
        $normalizados = [];
        foreach ($vinculos as $i => $v) {
            $mesa = Mesa::find((int) $v['mesa_id']);
            if (! $mesa || ! $mesa->ativo || $mesa->status === Mesa::STATUS_BLOQUEADA) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa inválida na composição.'];
            }
            if ((int) $mesa->unidade_id !== $unidadeId) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Todas as mesas devem ser da mesma unidade.'];
            }
            if ($i > 0) {
                $primeira = Mesa::find((int) $vinculos[0]['mesa_id']);
                if ($primeira && ! $primeira->podeSerCombinadaCom($mesa)) {
                    return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Grupo de composição incompatível ou mesas não permitem junção.'];
                }
            }
            $usa = (int) ($v['capacidade_utilizada'] ?? $mesa->capacidadeBase());
            $extras = (int) ($v['cadeiras_extras_utilizadas'] ?? max(0, $usa - $mesa->capacidadeBase()));
            if ($extras > 0 && ! $mesa->permiteAdicionarCadeiras()) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Mesa não permite cadeiras extras.'];
            }
            if ($extras > (int) ($mesa->cadeiras_extras_max ?? 0)) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Excede o máximo de cadeiras extras da mesa.'];
            }
            if ($usa > $mesa->capacidadeMaximaCalculada()) {
                return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Capacidade máxima da mesa excedida.'];
            }
            $capTotal += $usa;
            $normalizados[] = [
                'mesa_id' => (int) $mesa->id,
                'principal' => ! empty($v['principal']),
                'capacidade_utilizada' => $usa,
                'cadeiras_extras_utilizadas' => $extras,
                'configuracao_emergencial' => ! empty($v['configuracao_emergencial']) || count($vinculos) > 1 || $extras > 0,
                'numero_mesa' => (string) $mesa->numero_mesa,
                'nome' => $mesa->nome_mesa ?: ('Mesa '.$mesa->numero_mesa),
            ];
        }

        if ($capTotal < (int) $dados['qtd_pessoas']) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Capacidade total da composição insuficiente.'];
        }

        $payload = $this->payloadBaseDeNormalizado($dados);
        $principal = collect($normalizados)->firstWhere('principal', true) ?? $normalizados[0];
        $payload['mesa_id'] = (int) $principal['mesa_id'];

        return ['ok' => true, 'payload' => $payload, 'vinculos' => $normalizados];
    }

    /**
     * @param  array<int, array<string, mixed>>  $vinculos
     */
    private function sincronizarVinculos(ReservaMesa $reserva, array $vinculos): void
    {
        if (! Schema::hasTable('reserva_mesas')) {
            return;
        }

        DB::table('reserva_mesas')->where('reserva_id', $reserva->id)->delete();
        $now = now();
        foreach ($vinculos as $v) {
            DB::table('reserva_mesas')->insert([
                'reserva_id' => $reserva->id,
                'mesa_id' => (int) $v['mesa_id'],
                'capacidade_utilizada' => (int) ($v['capacidade_utilizada'] ?? $reserva->qtd_pessoas),
                'cadeiras_extras_utilizadas' => (int) ($v['cadeiras_extras_utilizadas'] ?? 0),
                'principal' => ! empty($v['principal']),
                'configuracao_emergencial' => ! empty($v['configuracao_emergencial']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $vinculos
     * @return array<string, mixed>|null
     */
    private function criarAlertaOperacionalSeNecessario(ReservaMesa $reserva, array $vinculos, ?int $usuarioId): ?array
    {
        if (! $this->vinculoEhEmergencial($vinculos) && count($vinculos) <= 1) {
            $extras = array_sum(array_map(fn ($v) => (int) ($v['cadeiras_extras_utilizadas'] ?? 0), $vinculos));
            if ($extras < 1) {
                return null;
            }
        }

        return $this->criarAlertaOperacional([
            'reserva_id' => $reserva->id,
            'unidade_id' => $reserva->unidade_id,
            'mesas' => $vinculos,
            'cliente' => $reserva->nome_cliente,
            'quantidade_pessoas' => $reserva->qtd_pessoas,
            'data' => $reserva->data_reserva?->format('Y-m-d'),
            'horario' => substr((string) $reserva->hora_reserva, 0, 5),
        ], $usuarioId)['data'] ?? null;
    }

    /** @param  array<int, array<string, mixed>>  $vinculos */
    private function vinculoEhEmergencial(array $vinculos): bool
    {
        foreach ($vinculos as $v) {
            if (! empty($v['configuracao_emergencial']) || (int) ($v['cadeiras_extras_utilizadas'] ?? 0) > 0) {
                return true;
            }
        }

        return count($vinculos) > 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $vinculos
     */
    private function textoResumoComposicao(array $payload, array $vinculos): string
    {
        $linhas = ["Não há uma mesa individual para {$payload['qtd_pessoas']} pessoas.", '', 'Posso juntar:'];
        $total = 0;
        foreach ($vinculos as $v) {
            $cap = (int) ($v['capacidade_utilizada'] ?? 0);
            $total += $cap;
            $nome = $v['nome'] ?? ('Mesa '.($v['mesa_id'] ?? '?'));
            $linhas[] = "- {$nome}: {$cap} lugares";
        }
        $linhas[] = '';
        $linhas[] = "Capacidade total: {$total} pessoas.";

        return implode("\n", $linhas);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function payloadBaseDeNormalizado(array $dados): array
    {
        return [
            'unidade_id' => (int) $dados['unidade_id'],
            'mesa_id' => (int) ($dados['mesa_id'] ?? 0),
            'nome_cliente' => trim((string) $dados['nome_cliente']),
            'telefone_cliente' => isset($dados['telefone_cliente']) ? trim((string) $dados['telefone_cliente']) : null,
            'data_reserva' => (string) $dados['data_reserva'],
            'hora_reserva' => $this->normalizarHorarioCurto((string) $dados['hora_reserva']),
            'qtd_pessoas' => (int) $dados['qtd_pessoas'],
            'status' => $dados['status'] ?? ReservaMesa::STATUS_PENDENTE,
            'observacao' => isset($dados['observacao']) ? mb_substr(trim((string) $dados['observacao']), 0, 500) : null,
            'local' => isset($dados['local']) ? mb_substr(trim((string) $dados['local']), 0, 100) : null,
            'ocasiao' => isset($dados['ocasiao']) ? mb_substr(trim((string) $dados['ocasiao']), 0, 255) : null,
            'forcar_duplicidade' => ! empty($dados['forcar_duplicidade']),
            'configuracao_emergencial' => ! empty($dados['configuracao_emergencial']),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function previewMesaEmergencial(array $dados, bool $comReserva): array
    {
        $mesaDados = is_array($dados['mesa'] ?? null) ? $dados['mesa'] : $dados;
        $reservaDados = is_array($dados['reserva'] ?? null) ? $dados['reserva'] : [];

        $base = max(1, (int) ($mesaDados['capacidade_base'] ?? $mesaDados['capacidade'] ?? 1));
        $extrasMax = max(0, (int) ($mesaDados['cadeiras_extras_max'] ?? 0));
        $maxima = (int) ($mesaDados['capacidade_maxima'] ?? ($base + $extrasMax));
        $numero = trim((string) ($mesaDados['numero_mesa'] ?? $mesaDados['nome'] ?? $mesaDados['nome_mesa'] ?? ''));
        $unidadeId = (int) ($mesaDados['unidade_id'] ?? $reservaDados['unidade_id'] ?? 0);

        if ($numero === '' || $unidadeId < 1) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Informe número/nome da mesa e unidade_id.'];
        }
        if (! AylaSettings::unidadePermitida($unidadeId)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }

        $payload = [
            'mesa' => [
                'numero_mesa' => $numero,
                'nome_mesa' => (string) ($mesaDados['nome'] ?? $mesaDados['nome_mesa'] ?? $numero),
                'unidade_id' => $unidadeId,
                'localizacao' => $mesaDados['localizacao'] ?? null,
                'capacidade' => $base,
                'capacidade_base' => $base,
                'permite_cadeiras_extras' => ! empty($mesaDados['permite_cadeiras_extras']) || $extrasMax > 0,
                'cadeiras_extras_max' => $extrasMax,
                'capacidade_maxima' => $maxima,
                'pode_juntar' => ! empty($mesaDados['pode_juntar']),
                'pode_separar' => ! empty($mesaDados['pode_separar']),
                'grupo_composicao' => $mesaDados['grupo_composicao'] ?? null,
                'cadastro_emergencial' => true,
                'cadastrado_pela_ayla' => true,
                'motivo_cadastro' => (string) ($mesaDados['motivo_cadastro'] ?? 'Cadastro emergencial pela Ayla para atendimento ao cliente.'),
            ],
        ];

        if ($comReserva) {
            $payload['reserva'] = $this->normalizarDadosCriacao(array_merge($reservaDados, [
                'unidade_id' => $unidadeId,
            ]));
        }

        $texto = "Resumo do cadastro emergencial:\n\n"
            ."Mesa: {$payload['mesa']['nome_mesa']}\n"
            ."Unidade: {$unidadeId}\n"
            .'Localização: '.($payload['mesa']['localizacao'] ?? '—')."\n"
            ."Capacidade base: {$base}\n"
            ."Cadeiras extras máximas: {$extrasMax}\n"
            ."Capacidade máxima: {$maxima}\n"
            .'Pode juntar: '.($payload['mesa']['pode_juntar'] ? 'Sim' : 'Não')."\n\n"
            .'Deseja confirmar o cadastro'.($comReserva ? ' e usar essa mesa na reserva' : '').'?';

        return [
            'ok' => true,
            'data' => [
                'payload' => $payload,
                'resumo' => $payload,
                'resumo_texto' => $texto,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function previewAjusteCapacidade(array $dados): array
    {
        $mesaId = (int) ($dados['mesa_id'] ?? 0);
        $novaMax = (int) ($dados['capacidade_maxima'] ?? $dados['capacidade_desejada'] ?? 0);
        $mesa = Mesa::find($mesaId);
        if (! $mesa) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Mesa não encontrada.'];
        }
        if (! AylaSettings::unidadePermitida((int) $mesa->unidade_id)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }
        if ($novaMax < $mesa->capacidadeBase()) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Capacidade máxima não pode ser menor que a base.'];
        }

        $extras = $novaMax - $mesa->capacidadeBase();
        $texto = sprintf(
            "A Mesa %s possui capacidade cadastrada para %d pessoas.\n\nPara atender, será necessário acrescentar %d cadeira(s).\nDeseja autorizar o ajuste da capacidade máxima dessa mesa para %d pessoas?",
            $mesa->nome_mesa ?: $mesa->numero_mesa,
            $mesa->capacidadeBase(),
            $extras,
            $novaMax
        );

        $payload = array_merge($dados, [
            'mesa_id' => $mesaId,
            'capacidade_anterior' => $mesa->capacidadeMaximaCalculada(),
            'capacidade_base' => $mesa->capacidadeBase(),
            'cadeiras_extras_max' => $extras,
            'capacidade_maxima' => $novaMax,
            'permite_cadeiras_extras' => true,
        ]);

        return [
            'ok' => true,
            'data' => [
                'payload' => $payload,
                'resumo' => $payload,
                'resumo_texto' => $texto,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    private function previewAlertaOperacional(array $dados): array
    {
        $texto = 'Criar alerta/tarefa operacional: '.($dados['titulo'] ?? ('Preparar estrutura da reserva #'.($dados['reserva_id'] ?? '?')))
            ."\n\nDeseja confirmar?";

        return [
            'ok' => true,
            'data' => [
                'payload' => $dados,
                'resumo' => $dados,
                'resumo_texto' => $texto,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function criarMesaEmergencial(array $dados, ?int $usuarioId = null): array
    {
        if (! Schema::hasTable('mesas')) {
            return ['ok' => false, 'code' => 'UNAVAILABLE', 'message' => 'Tabela de mesas indisponível.'];
        }

        $base = max(1, (int) ($dados['capacidade_base'] ?? $dados['capacidade'] ?? 1));
        $extras = max(0, (int) ($dados['cadeiras_extras_max'] ?? 0));
        $maxima = (int) ($dados['capacidade_maxima'] ?? ($base + $extras));
        $unidadeId = (int) ($dados['unidade_id'] ?? 0);
        $numero = trim((string) ($dados['numero_mesa'] ?? $dados['nome'] ?? $dados['nome_mesa'] ?? ''));

        if ($unidadeId < 1 || $numero === '') {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'unidade_id e número/nome da mesa são obrigatórios.'];
        }
        if (! AylaSettings::unidadePermitida($unidadeId)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }

        $existe = Mesa::where('unidade_id', $unidadeId)->where('numero_mesa', $numero)->where('ativo', true)->exists();
        if ($existe) {
            return ['ok' => false, 'code' => 'VALIDATION_ERROR', 'message' => 'Já existe uma mesa com esse número nesta unidade.'];
        }

        $attrs = [
            'unidade_id' => $unidadeId,
            'numero_mesa' => $numero,
            'nome_mesa' => (string) ($dados['nome_mesa'] ?? $dados['nome'] ?? $numero),
            'capacidade' => $base,
            'localizacao' => $dados['localizacao'] ?? null,
            'pode_juntar' => ! empty($dados['pode_juntar']),
            'pode_separar' => ! empty($dados['pode_separar']),
            'status' => Mesa::STATUS_LIVRE,
            'observacao' => $dados['observacao'] ?? null,
            'ativo' => true,
        ];

        if (Schema::hasColumn('mesas', 'capacidade_base')) {
            $attrs['capacidade_base'] = $base;
            $attrs['permite_cadeiras_extras'] = ! empty($dados['permite_cadeiras_extras']) || $extras > 0;
            $attrs['cadeiras_extras_max'] = $extras;
            $attrs['capacidade_maxima'] = $maxima;
            $attrs['grupo_composicao'] = $dados['grupo_composicao'] ?? null;
            $attrs['cadastro_emergencial'] = true;
            $attrs['cadastrado_pela_ayla'] = true;
            $attrs['cadastrado_por_usuario_id'] = $usuarioId;
            $attrs['motivo_cadastro'] = (string) ($dados['motivo_cadastro'] ?? 'Cadastro emergencial pela Ayla');
        }

        $mesa = Mesa::create($attrs);

        return [
            'ok' => true,
            'data' => [
                'mensagem' => 'A mesa foi cadastrada no sistema. Atenção: organize fisicamente a mesa e as cadeiras antes do horário do cliente.',
                'mesa' => $mesa->toArray(),
                'mesa_criada' => true,
                'cadastro_emergencial' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function criarMesaEmergencialEReservar(array $payload, ?int $usuarioId = null): array
    {
        $mesaPayload = is_array($payload['mesa'] ?? null) ? $payload['mesa'] : $payload;
        $criada = $this->criarMesaEmergencial($mesaPayload, $usuarioId);
        if (! ($criada['ok'] ?? false)) {
            return $criada;
        }

        $mesaId = (int) ($criada['data']['mesa']['id'] ?? 0);
        $reservaPayload = $this->normalizarDadosCriacao(is_array($payload['reserva'] ?? null) ? $payload['reserva'] : []);
        $reservaPayload['mesa_id'] = $mesaId;
        $reservaPayload['unidade_id'] = (int) ($mesaPayload['unidade_id'] ?? $reservaPayload['unidade_id'] ?? 0);
        $reservaPayload['configuracao_emergencial'] = true;
        $reservaPayload['forcar_duplicidade'] = true;

        $qtd = (int) ($reservaPayload['qtd_pessoas'] ?? 0);
        $base = (int) ($criada['data']['mesa']['capacidade_base'] ?? $criada['data']['mesa']['capacidade'] ?? 0);
        $reservaPayload['mesas'] = [[
            'mesa_id' => $mesaId,
            'principal' => true,
            'capacidade_utilizada' => $qtd,
            'cadeiras_extras_utilizadas' => max(0, $qtd - $base),
            'configuracao_emergencial' => true,
        ]];

        $reserva = $this->criarReserva($reservaPayload, $usuarioId);
        if (! ($reserva['ok'] ?? false)) {
            return $reserva;
        }

        return [
            'ok' => true,
            'data' => array_merge($reserva['data'] ?? [], [
                'mesa' => $criada['data']['mesa'] ?? null,
                'mensagem' => 'A mesa foi cadastrada no sistema e a reserva foi criada. Atenção: organize fisicamente a mesa e as cadeiras antes do horário do cliente.',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function ajustarCapacidadeMesa(array $dados, ?int $usuarioId = null): array
    {
        $mesaId = (int) ($dados['mesa_id'] ?? 0);
        $mesa = Mesa::find($mesaId);
        if (! $mesa) {
            return ['ok' => false, 'code' => 'NOT_FOUND', 'message' => 'Mesa não encontrada.'];
        }
        if (! AylaSettings::unidadePermitida((int) $mesa->unidade_id)) {
            return ['ok' => false, 'code' => 'UNIT_NOT_ALLOWED', 'message' => 'Unidade não autorizada.'];
        }

        $anterior = [
            'capacidade' => (int) $mesa->capacidade,
            'capacidade_base' => $mesa->capacidadeBase(),
            'cadeiras_extras_max' => (int) ($mesa->cadeiras_extras_max ?? 0),
            'capacidade_maxima' => $mesa->capacidadeMaximaCalculada(),
        ];

        $base = (int) ($dados['capacidade_base'] ?? $mesa->capacidadeBase());
        $maxima = (int) ($dados['capacidade_maxima'] ?? 0);
        $extras = (int) ($dados['cadeiras_extras_max'] ?? max(0, $maxima - $base));
        if ($maxima < 1) {
            $maxima = $base + $extras;
        }

        $update = [];
        // Preserva capacidade física cadastrada (capacidade/capacidade_base).
        if (Schema::hasColumn('mesas', 'capacidade_base')) {
            $update['capacidade_base'] = $base;
            $update['permite_cadeiras_extras'] = true;
            $update['cadeiras_extras_max'] = $extras;
            $update['capacidade_maxima'] = $maxima;
        } else {
            // Fallback legado: só então altera capacidade.
            $update['capacidade'] = $maxima;
        }

        $mesa->update($update);

        $alerta = null;
        if (! empty($dados['criar_reserva_apos']) && is_array($dados['reserva'] ?? null)) {
            $dados['reserva']['mesa_id'] = $mesaId;
            $dados['reserva']['unidade_id'] = (int) $mesa->unidade_id;
            $dados['reserva']['configuracao_emergencial'] = true;
            $dados['reserva']['forcar_duplicidade'] = true;
            $dados['reserva']['mesas'] = [[
                'mesa_id' => $mesaId,
                'principal' => true,
                'capacidade_utilizada' => (int) ($dados['reserva']['qtd_pessoas'] ?? $dados['reserva']['quantidade_pessoas'] ?? $maxima),
                'cadeiras_extras_utilizadas' => max(0, (int) ($dados['reserva']['qtd_pessoas'] ?? $maxima) - $base),
                'configuracao_emergencial' => true,
            ]];
            $reserva = $this->criarReserva($this->normalizarDadosCriacao($dados['reserva']), $usuarioId);
            if (! ($reserva['ok'] ?? false)) {
                return $reserva;
            }
            $alerta = $reserva['data']['alerta_operacional'] ?? null;

            return [
                'ok' => true,
                'data' => array_merge($reserva['data'] ?? [], [
                    'capacidade_anterior' => $anterior,
                    'capacidade_nova' => [
                        'capacidade_base' => $base,
                        'cadeiras_extras_max' => $extras,
                        'capacidade_maxima' => $maxima,
                    ],
                    'alerta_operacional' => $alerta,
                    'mensagem' => 'Capacidade ajustada e reserva criada. Organize fisicamente as cadeiras extras.',
                ]),
            ];
        }

        $alerta = $this->criarAlertaOperacional([
            'unidade_id' => (int) $mesa->unidade_id,
            'mesa_id' => $mesaId,
            'titulo' => 'Ajustar cadeiras da Mesa '.($mesa->nome_mesa ?: $mesa->numero_mesa),
            'quantidade_pessoas' => $maxima,
            'observacoes' => 'Ajuste emergencial de capacidade pela Ayla.',
        ], $usuarioId)['data'] ?? null;

        return [
            'ok' => true,
            'data' => [
                'mensagem' => 'Capacidade máxima atualizada. Capacidade física base preservada.',
                'mesa' => $mesa->fresh()->toArray(),
                'capacidade_anterior' => $anterior,
                'capacidade_nova' => [
                    'capacidade_base' => $base,
                    'cadeiras_extras_max' => $extras,
                    'capacidade_maxima' => $maxima,
                ],
                'alerta_operacional' => $alerta,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{ok: bool, code?: string, message?: string, data?: array<string, mixed>}
     */
    public function criarAlertaOperacional(array $dados, ?int $usuarioId = null): array
    {
        $reservaId = (int) ($dados['reserva_id'] ?? 0);
        $unidadeId = (int) ($dados['unidade_id'] ?? 0);
        $titulo = (string) ($dados['titulo'] ?? ('Preparar estrutura da reserva #'.($reservaId ?: '?')));
        $data = (string) ($dados['data'] ?? '');
        $horario = (string) ($dados['horario'] ?? '');
        $prazo = null;
        if ($data !== '' && $horario !== '') {
            try {
                $prazo = Carbon::parse($data.' '.$horario)->subMinutes(30)->toDateString();
            } catch (\Throwable $e) {
                $prazo = $data;
            }
        }

        $descricao = "Preparar estrutura física\n"
            .'Unidade: '.$unidadeId."\n"
            .'Data: '.$data."\n"
            .'Horário: '.$horario."\n"
            .'Cliente: '.($dados['cliente'] ?? '—')."\n"
            .'Pessoas: '.($dados['quantidade_pessoas'] ?? '—')."\n"
            .'Mesas: '.json_encode($dados['mesas'] ?? $dados['mesa_id'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            .'Obs: '.($dados['observacoes'] ?? '');

        $task = null;
        if (Schema::hasTable('kanban_tasks')) {
            try {
                $task = \App\Models\KanbanTask::create([
                    'titulo' => mb_substr($titulo, 0, 255),
                    'descricao' => $descricao,
                    'unidade_id' => $unidadeId > 0 ? $unidadeId : null,
                    'setor' => 'Reservas',
                    'responsavel' => $dados['responsavel'] ?? 'gerente',
                    'prioridade' => 'alta',
                    'status' => 'pendente',
                    'prazo' => $prazo,
                    'observacoes' => 'Gerado automaticamente pela Ayla. Não concluir automaticamente.',
                ]);
            } catch (\Throwable $e) {
                $task = null;
            }
        }

        // Sempre registra alerta na observação da reserva, se houver.
        if ($reservaId > 0) {
            $reserva = ReservaMesa::find($reservaId);
            if ($reserva) {
                $nota = '[ALERTA OPERACIONAL AYLA] '.$titulo.' — organize mesas/cadeiras 30 min antes.';
                $obs = trim((string) ($reserva->observacao ?? ''));
                $reserva->update(['observacao' => $obs !== '' ? $obs."\n".$nota : $nota]);
            }
        }

        return [
            'ok' => true,
            'data' => [
                'kanban_task_id' => $task?->id,
                'titulo' => $titulo,
                'prazo' => $prazo,
                'descricao' => $descricao,
                'registrado_na_reserva' => $reservaId > 0,
            ],
        ];
    }
}
