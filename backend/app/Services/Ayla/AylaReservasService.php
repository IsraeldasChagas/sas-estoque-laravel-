<?php

namespace App\Services\Ayla;

use App\Support\Ayla\AylaSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas somente leitura do módulo Reservas de Mesas para a Ayla.
 *
 * Campos reais: reservas_mesas (nome_cliente, telefone_cliente, data_reserva,
 * hora_reserva, qtd_pessoas, status, observacao, local, ocasiao) e mesas
 * (capacidade, status, numero_mesa). Sem duração nem data_hora composta.
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
        $unidadeId = (int) ($args['unidade_id'] ?? 0);
        $data = (string) ($args['data'] ?? '');
        $horario = $this->normalizarHorario((string) ($args['horario'] ?? ''));
        $qtd = isset($args['quantidade_pessoas']) ? (int) $args['quantidade_pessoas'] : null;
        // Duração não existe no banco; parâmetro aceito e ignorado com nota.
        $duracao = isset($args['duracao_minutos']) ? (int) $args['duracao_minutos'] : null;

        if (! Schema::hasTable('mesas')) {
            return [
                'unidade_id' => $unidadeId,
                'data' => $data,
                'horario' => $horario,
                'mesas_disponiveis' => [],
                'mesas_ocupadas' => [],
                'capacidade_total_disponivel' => 0,
                'observacoes' => ['Tabela de mesas indisponível.'],
                'sugestao' => null,
            ];
        }

        $mesas = DB::table('mesas as m')
            ->where('m.unidade_id', $unidadeId)
            ->where('m.ativo', 1)
            ->where('m.status', '!=', 'bloqueada')
            ->orderBy('m.numero_mesa')
            ->get(['m.id', 'm.numero_mesa', 'm.nome_mesa', 'm.capacidade', 'm.status', 'm.localizacao']);

        $ocupadasIds = [];
        if (Schema::hasTable('reservas_mesas') && $data !== '' && $horario !== null) {
            $ocupadasIds = DB::table('reservas_mesas')
                ->where('unidade_id', $unidadeId)
                ->whereDate('data_reserva', $data)
                ->whereTime('hora_reserva', $horario)
                ->whereIn('status', self::STATUS_ATIVAS)
                ->pluck('mesa_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $disponiveis = [];
        $ocupadas = [];
        $notas = [];

        if ($duracao !== null && $duracao > 0) {
            $notas[] = 'O sistema não armazena duração de reserva; o conflito é por data+horário exatos.';
        }

        foreach ($mesas as $m) {
            $item = [
                'mesa_id' => (int) $m->id,
                'numero_mesa' => (string) $m->numero_mesa,
                'nome' => $m->nome_mesa ? (string) $m->nome_mesa : ('Mesa '.$m->numero_mesa),
                'capacidade' => (int) $m->capacidade,
                'status_mesa' => (string) $m->status,
                'localizacao' => $m->localizacao ? (string) $m->localizacao : null,
            ];

            $ocupadaSlot = in_array((int) $m->id, $ocupadasIds, true);
            $capOk = $qtd === null || $qtd <= (int) $m->capacidade;

            if ($ocupadaSlot) {
                $item['motivo'] = 'Já possui reserva ativa neste horário.';
                $ocupadas[] = $item;
            } elseif (! $capOk) {
                $item['motivo'] = 'Capacidade insuficiente ('.$m->capacidade.' lugares).';
                $ocupadas[] = $item;
            } else {
                $disponiveis[] = $item;
            }
        }

        $capDisp = array_sum(array_column($disponiveis, 'capacidade'));

        usort($disponiveis, function ($a, $b) use ($qtd) {
            if ($qtd === null) {
                return $a['capacidade'] <=> $b['capacidade'];
            }
            // Melhor mesa: capacidade suficiente mais próxima da quantidade.
            $da = abs($a['capacidade'] - $qtd);
            $db = abs($b['capacidade'] - $qtd);

            return $da <=> $db;
        });

        $sugestao = $disponiveis[0] ?? null;

        return [
            'unidade_id' => $unidadeId,
            'data' => $data,
            'horario' => $horario,
            'quantidade_pessoas' => $qtd,
            'mesas_disponiveis' => $disponiveis,
            'mesas_ocupadas' => $ocupadas,
            'capacidade_total_disponivel' => $capDisp,
            'total_disponiveis' => count($disponiveis),
            'total_ocupadas' => count($ocupadas),
            'observacoes' => $notas,
            'sugestao' => $sugestao,
        ];
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
}
