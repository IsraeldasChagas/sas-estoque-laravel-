<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use App\Services\Fidelidade\ReservaFidelidadeService;
use App\Services\Reservas\ReservaMeioPagamentoService;
use App\Support\ReservaMesaAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ReservaMesaController extends Controller
{
    protected function podeGerenciarTodasUnidades(?object $usuario): bool
    {
        return ReservaMesaAcesso::podeGerenciarTodasUnidades($usuario);
    }

    /**
     * Resolve a unidade efetiva para a requisição.
     * - Quem tem acesso ao módulo Reserva de Mesa: pode usar unidade_id do request.
     * - Sem acesso: unidade cadastrada do usuário.
     */
    protected function resolveUnidadeId(Request $request, ?object $usuario): ?int
    {
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        if (! $this->podeGerenciarTodasUnidades($usuario)) {
            return $unidadeIdUsuario > 0 ? $unidadeIdUsuario : null;
        }

        if ($request->filled('unidade_id')) {
            $u = (int) $request->unidade_id;
            return $u > 0 ? $u : null;
        }

        return $unidadeIdUsuario > 0 ? $unidadeIdUsuario : null;
    }

    /** Retorna 403 se usuário comum tentar operar fora da unidade cadastrada. */
    protected function assertUnidadeDoUsuarioOu403(Request $request, ?object $usuario): ?\Illuminate\Http\JsonResponse
    {
        if ($this->podeGerenciarTodasUnidades($usuario)) {
            return null;
        }

        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if ($unidadeIdUsuario <= 0) {
            return response()->json(['message' => 'Usuário sem unidade cadastrada.'], 403);
        }

        if ($request->filled('unidade_id') && (int) $request->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para acessar outra unidade.'], 403);
        }

        return null;
    }

    /** Normaliza hora para H:i (alguns browsers enviam HH:MM:SS e a regra date_format:H:i falha). */
    protected function normalizeHoraReservaRequest(Request $request): void
    {
        $h = $request->input('hora_reserva');
        if (! is_string($h) || trim($h) === '') {
            return;
        }
        $h = trim($h);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $h, $m)) {
            $request->merge(['hora_reserva' => sprintf('%02d:%02d', (int) $m[1], (int) $m[2])]);
        }
    }

    public function index(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);

        // Sem unidade definida = não devolver reservas de outras unidades
        if (!$unidadeId) {
            return response()->json([]);
        }

        $query = ReservaMesa::with(['mesa:id,numero_mesa,nome_mesa,capacidade,unidade_id', 'usuario:id,nome'])
            ->where('unidade_id', '=', $unidadeId);

        if ($request->filled('data_reserva')) {
            $query->where('data_reserva', $request->data_reserva);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Quando não há filtro de status, mostramos apenas reservas ativas,
            // igual ao resumo e à contagem de mesas (evita divergência por canceladas).
            $query->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);
        }

        if ($request->filled('turno')) {
            $hora = (int) $request->turno;
            $query->whereRaw('HOUR(hora_reserva) >= ?', [$hora])
                  ->whereRaw('HOUR(hora_reserva) < ?', [$hora + 4]);
        }

        $reservas = $query->orderBy('data_reserva')->orderBy('hora_reserva')->get();

        return response()->json($reservas);
    }

    public function resumo(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        if (!$unidadeId) {
            return response()->json([
                'total_mesas' => 0,
                'mesas_livres' => 0,
                'mesas_reservadas' => 0,
                'mesas_ocupadas' => 0,
                'mesas_aguardando_cliente' => 0,
                'total_reservas_dia' => 0,
            ]);
        }

        $dataReserva = $request->get('data_reserva', date('Y-m-d'));

        $queryMesas = Mesa::where('ativo', true)->where('unidade_id', $unidadeId);
        $totalMesas = $queryMesas->count();

        $queryReservas = ReservaMesa::where('unidade_id', $unidadeId)
            ->where('data_reserva', $dataReserva)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);
        $reservasAtivas = $queryReservas->get();

        $mesasIdsComReserva = $reservasAtivas->pluck('mesa_id')->unique();
        $livres = $totalMesas - $mesasIdsComReserva->count();
        $reservadas = $reservasAtivas->whereIn('status', ['pendente', 'confirmada'])->count();
        $ocupadas = $reservasAtivas->where('status', 'cliente_chegou')->count();
        $aguardando = $reservasAtivas->whereIn('status', ['pendente', 'confirmada'])->count();

        return response()->json([
            'total_mesas' => $totalMesas,
            'mesas_livres' => max(0, $livres),
            'mesas_reservadas' => $reservadas,
            'mesas_ocupadas' => $ocupadas,
            'mesas_aguardando_cliente' => $aguardando,
            'total_reservas_dia' => $reservasAtivas->count(),
        ]);
    }

    /**
     * Dashboard operacional de reservas (hoje + tendência + próximos horários).
     */
    public function dashboard(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        $hoje = Carbon::today()->toDateString();
        $dias = max(7, min(30, (int) $request->get('dias', 14)));
        $inicio = Carbon::today()->subDays($dias - 1)->toDateString();

        if (! $unidadeId) {
            return response()->json([
                'unidade_id' => null,
                'hoje' => $hoje,
                'kpis' => [
                    'reservas_hoje' => 0,
                    'pessoas_hoje' => 0,
                    'confirmadas' => 0,
                    'pendentes' => 0,
                    'chegaram' => 0,
                    'no_show' => 0,
                    'canceladas' => 0,
                    'mesas_livres' => 0,
                    'mesas_total' => 0,
                    'ocupacao_pct' => 0,
                    'taxa_no_show_pct' => 0,
                    'taxa_cancelamento_pct' => 0,
                ],
                'por_status' => [],
                'por_turno' => ['almoco' => 0, 'tarde' => 0, 'noite' => 0],
                'serie_dias' => [],
                'proximas' => [],
                'top_clientes' => [],
                'insights' => ['Selecione uma unidade para ver o painel de reservas.'],
            ]);
        }

        $mesasTotal = Mesa::where('ativo', true)->where('unidade_id', $unidadeId)->count();

        $reservasHoje = ReservaMesa::with(['mesa:id,numero_mesa,nome_mesa'])
            ->where('unidade_id', $unidadeId)
            ->whereDate('data_reserva', $hoje)
            ->orderBy('hora_reserva')
            ->get();

        $ativasHoje = $reservasHoje->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);
        $mesasOcupadasIds = $ativasHoje->pluck('mesa_id')->unique()->count();
        $livres = max(0, $mesasTotal - $mesasOcupadasIds);

        $countStatus = function ($status) use ($reservasHoje) {
            return $reservasHoje->where('status', $status)->count();
        };

        $confirmadas = $countStatus('confirmada');
        $pendentes = $countStatus('pendente');
        $chegaram = $countStatus('cliente_chegou');
        $noShow = $countStatus('no_show');
        $canceladas = $countStatus('cancelada');
        $finalizadas = $countStatus('finalizada');
        $pessoasHoje = (int) $ativasHoje->sum('qtd_pessoas');

        $periodo = ReservaMesa::where('unidade_id', $unidadeId)
            ->whereDate('data_reserva', '>=', $inicio)
            ->whereDate('data_reserva', '<=', $hoje)
            ->get(['data_reserva', 'status', 'qtd_pessoas', 'nome_cliente', 'telefone_cliente', 'hora_reserva']);

        $totalPeriodo = $periodo->count();
        $noShowPeriodo = $periodo->where('status', 'no_show')->count();
        $cancelPeriodo = $periodo->where('status', 'cancelada')->count();

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = Carbon::today()->subDays($i)->toDateString();
            $doDia = $periodo->filter(fn ($r) => Carbon::parse($r->data_reserva)->toDateString() === $dia);
            $serie[] = [
                'data' => $dia,
                'label' => Carbon::parse($dia)->format('d/m'),
                'total' => $doDia->count(),
                'ativas' => $doDia->whereNotIn('status', ['cancelada', 'no_show'])->count(),
                'pessoas' => (int) $doDia->whereNotIn('status', ['cancelada', 'no_show'])->sum('qtd_pessoas'),
                'no_show' => $doDia->where('status', 'no_show')->count(),
                'canceladas' => $doDia->where('status', 'cancelada')->count(),
            ];
        }

        $horaAgora = Carbon::now()->format('H:i:s');
        $proximas = $ativasHoje
            ->filter(fn ($r) => (string) $r->hora_reserva >= $horaAgora)
            ->take(8)
            ->values()
            ->map(function ($r) {
                $mesa = $r->mesa;

                return [
                    'id' => $r->id,
                    'hora' => substr((string) $r->hora_reserva, 0, 5),
                    'cliente' => $r->nome_cliente,
                    'telefone' => $r->telefone_cliente,
                    'pessoas' => (int) $r->qtd_pessoas,
                    'status' => $r->status,
                    'mesa' => $mesa ? ($mesa->nome_mesa ?: $mesa->numero_mesa ?: ('Mesa '.$mesa->id)) : '—',
                ];
            });

        if ($proximas->isEmpty()) {
            $proximas = $ativasHoje->take(8)->values()->map(function ($r) {
                $mesa = $r->mesa;

                return [
                    'id' => $r->id,
                    'hora' => substr((string) $r->hora_reserva, 0, 5),
                    'cliente' => $r->nome_cliente,
                    'telefone' => $r->telefone_cliente,
                    'pessoas' => (int) $r->qtd_pessoas,
                    'status' => $r->status,
                    'mesa' => $mesa ? ($mesa->nome_mesa ?: $mesa->numero_mesa ?: ('Mesa '.$mesa->id)) : '—',
                ];
            });
        }

        $turno = ['almoco' => 0, 'tarde' => 0, 'noite' => 0];
        foreach ($ativasHoje as $r) {
            $h = (int) substr((string) $r->hora_reserva, 0, 2);
            if ($h < 15) {
                $turno['almoco']++;
            } elseif ($h < 18) {
                $turno['tarde']++;
            } else {
                $turno['noite']++;
            }
        }

        $topClientes = $periodo
            ->filter(fn ($r) => ! in_array($r->status, ['cancelada', 'no_show'], true))
            ->groupBy(function ($r) {
                $tel = preg_replace('/\D+/', '', (string) ($r->telefone_cliente ?? ''));

                return $tel !== '' ? $tel : mb_strtolower(trim((string) $r->nome_cliente));
            })
            ->map(function ($grupo) {
                $primeiro = $grupo->first();

                return [
                    'nome' => $primeiro->nome_cliente,
                    'telefone' => $primeiro->telefone_cliente,
                    'visitas' => $grupo->count(),
                    'pessoas' => (int) $grupo->sum('qtd_pessoas'),
                ];
            })
            ->sortByDesc('visitas')
            ->take(5)
            ->values();

        $ocupacaoPct = $mesasTotal > 0 ? round(($mesasOcupadasIds / $mesasTotal) * 100) : 0;
        $insights = [];
        if ($ativasHoje->count() === 0) {
            $insights[] = 'Nenhuma reserva ativa para hoje — boa hora para campanha no WhatsApp.';
        } elseif ($ocupacaoPct >= 80) {
            $insights[] = 'Ocupação alta hoje ('.$ocupacaoPct.'%). Prepare mesas emergenciais se precisar.';
        } elseif ($ocupacaoPct <= 30 && $ativasHoje->count() > 0) {
            $insights[] = 'Ainda há bastante mesa livre ('.$livres.'). Dá para aceitar walk-ins.';
        }
        if ($pendentes > 0) {
            $insights[] = $pendentes.' reserva(s) pendente(s) aguardando confirmação.';
        }
        if ($noShowPeriodo > 0 && $totalPeriodo > 0) {
            $insights[] = 'No-show no período: '.round(($noShowPeriodo / $totalPeriodo) * 100).'% — reforçar lembrete no WhatsApp.';
        }
        if ($turno['noite'] > $turno['almoco'] && $turno['noite'] > $turno['tarde']) {
            $insights[] = 'Hoje o pico está à noite — alinhe equipe e salão para o jantar.';
        } elseif ($turno['almoco'] >= $turno['noite'] && $turno['almoco'] > 0) {
            $insights[] = 'Almoço concentra a maior parte das reservas de hoje.';
        }
        if ($insights === []) {
            $insights[] = 'Operação estável. Acompanhe as próximas chegadas na lista ao lado.';
        }

        return response()->json([
            'unidade_id' => $unidadeId,
            'hoje' => $hoje,
            'kpis' => [
                'reservas_hoje' => $ativasHoje->count(),
                'pessoas_hoje' => $pessoasHoje,
                'confirmadas' => $confirmadas,
                'pendentes' => $pendentes,
                'chegaram' => $chegaram,
                'no_show' => $noShow,
                'canceladas' => $canceladas,
                'finalizadas' => $finalizadas,
                'mesas_livres' => $livres,
                'mesas_total' => $mesasTotal,
                'ocupacao_pct' => $ocupacaoPct,
                'taxa_no_show_pct' => $totalPeriodo > 0 ? round(($noShowPeriodo / $totalPeriodo) * 100, 1) : 0,
                'taxa_cancelamento_pct' => $totalPeriodo > 0 ? round(($cancelPeriodo / $totalPeriodo) * 100, 1) : 0,
            ],
            'por_status' => [
                ['key' => 'pendente', 'label' => 'Pendente', 'total' => $pendentes],
                ['key' => 'confirmada', 'label' => 'Confirmada', 'total' => $confirmadas],
                ['key' => 'cliente_chegou', 'label' => 'Chegou', 'total' => $chegaram],
                ['key' => 'finalizada', 'label' => 'Finalizada', 'total' => $finalizadas],
                ['key' => 'no_show', 'label' => 'No-show', 'total' => $noShow],
                ['key' => 'cancelada', 'label' => 'Cancelada', 'total' => $canceladas],
            ],
            'por_turno' => $turno,
            'serie_dias' => $serie,
            'proximas' => $proximas,
            'top_clientes' => $topClientes,
            'insights' => $insights,
        ]);
    }

    public function store(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        // Carregar mesa antecipadamente para fallback de unidade (funciona em todas as unidades)
        $mesa = $request->filled('mesa_id') ? Mesa::find($request->mesa_id) : null;
        if (!$mesa) {
            return response()->json(['message' => 'Mesa é obrigatória e deve existir.'], 422);
        }

        if (! $this->podeGerenciarTodasUnidades($usuario)) {
            if ($unidadeIdUsuario <= 0) {
                return response()->json(['message' => 'Usuário sem unidade cadastrada.'], 403);
            }
            if ((int) $mesa->unidade_id !== $unidadeIdUsuario) {
                return response()->json(['message' => 'Mesa não pertence à sua unidade.'], 403);
            }
        }

        // Definição da unidade da reserva (mesma regra para unidade 1, 2, etc.):
        // 1. Se vier unidade_id no request, usamos essa.
        // 2. Senão, se o usuário tiver unidade fixa, usamos a dele.
        // 3. Fallback: usar unidade da mesa (garante funcionar em qualquer unidade)
        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        if (! $unidadeId) {
            $unidadeId = (int) $mesa->unidade_id;
        }
        if ($unidadeId <= 0 || !DB::table('unidades')->where('id', $unidadeId)->exists()) {
            return response()->json(['message' => 'Unidade inválida ou não informada.'], 422);
        }
        if ((int) $mesa->unidade_id !== $unidadeId) {
            return response()->json(['message' => 'Mesa não pertence à unidade selecionada.'], 422);
        }
        $request->merge(['unidade_id' => $unidadeId]);

        $this->normalizeHoraReservaRequest($request);

        $validator = Validator::make($request->all(), [
            'unidade_id' => 'required|exists:unidades,id',
            'mesa_id' => 'required|exists:mesas,id',
            'nome_cliente' => 'required|string|max:255',
            'telefone_cliente' => 'nullable|string|max:30',
            'data_reserva' => 'required|date|after_or_equal:today',
            'hora_reserva' => 'required|date_format:H:i',
            'qtd_pessoas' => 'required|integer|min:1|max:99',
            'status' => 'nullable|in:pendente,confirmada,cancelada,cliente_chegou,no_show,finalizada',
            'observacao' => 'nullable|string|max:500',
            'local' => 'nullable|string|max:100',
            'ocasiao' => 'nullable|string|max:255',
            'cadeiras_extras_utilizadas' => 'nullable|integer|min:0|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        if (!$mesa->ativo) {
            return response()->json(['message' => 'Mesa está inativa ou bloqueada.'], 422);
        }
        if ($mesa->status === Mesa::STATUS_BLOQUEADA) {
            return response()->json(['message' => 'Mesa está bloqueada para reservas.'], 422);
        }
        $capBase = $mesa->capacidadeBase();
        $extrasReq = max(0, (int) $request->input('cadeiras_extras_utilizadas', 0));
        $capComExtras = min(99, $capBase + $extrasReq);
        if ($request->qtd_pessoas > $capComExtras) {
            return response()->json([
                'message' => "Com +{$extrasReq} cadeira(s) a capacidade é {$capComExtras} pessoa(s).",
                'errors' => ['qtd_pessoas' => ['Quantidade excede a capacidade com as cadeiras selecionadas.']]
            ], 422);
        }

        $conflito = ReservaMesa::where('mesa_id', $request->mesa_id)
            ->where('data_reserva', $request->data_reserva)
            ->where('hora_reserva', $request->hora_reserva)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->exists();

        if ($conflito) {
            return response()->json([
                'message' => 'Já existe uma reserva para esta mesa no mesmo horário.',
                'errors' => ['mesa_id' => ['Conflito de horário.']]
            ], 422);
        }

        $data = $request->only([
            'unidade_id', 'mesa_id', 'nome_cliente', 'telefone_cliente',
            'data_reserva', 'hora_reserva', 'qtd_pessoas', 'status', 'observacao', 'local', 'ocasiao'
        ]);
        $data['usuario_id'] = $usuarioId;
        $data['status'] = $data['status'] ?? ReservaMesa::STATUS_PENDENTE;

        $reserva = ReservaMesa::create($data);

        $mesa->update(['status' => Mesa::STATUS_RESERVADA]);
        $this->sincronizarVinculoPrincipal($reserva, $extrasReq);

        $reserva->load(['mesa:id,numero_mesa,nome_mesa,capacidade,capacidade_base,capacidade_maxima,permite_cadeiras_extras,cadeiras_extras_max', 'usuario:id,nome', 'unidade:id,nome,endereco,telefone']);
        $this->anexarMesasCompostas($reserva);
        return response()->json(['message' => 'Reserva criada com sucesso', 'reserva' => $reserva], 201);
    }

    public function show($id)
    {
        $reserva = ReservaMesa::with(['mesa:id,numero_mesa,nome_mesa,capacidade,capacidade_base,capacidade_maxima,permite_cadeiras_extras,cadeiras_extras_max,localizacao,unidade_id', 'usuario:id,nome', 'unidade:id,nome,endereco,telefone'])
            ->findOrFail($id);
        $usuarioId = request()->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $reserva->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para acessar esta reserva.'], 403);
        }
        $this->anexarMesasCompostas($reserva);
        return response()->json($reserva);
    }

    public function update(Request $request, $id)
    {
        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $reserva->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para editar esta reserva.'], 403);
        }

        if (in_array($reserva->status, [ReservaMesa::STATUS_CANCELADA, ReservaMesa::STATUS_FINALIZADA, ReservaMesa::STATUS_NO_SHOW])) {
            return response()->json(['message' => 'Reserva não pode ser editada neste status.'], 422);
        }

        $this->normalizeHoraReservaRequest($request);

        $validator = Validator::make($request->all(), [
            'mesa_id' => 'sometimes|required|exists:mesas,id',
            'nome_cliente' => 'sometimes|required|string|max:255',
            'telefone_cliente' => 'nullable|string|max:30',
            'data_reserva' => 'sometimes|required|date|after_or_equal:today',
            'hora_reserva' => 'sometimes|required|date_format:H:i',
            'qtd_pessoas' => 'sometimes|required|integer|min:1|max:99',
            'status' => 'nullable|in:pendente,confirmada,cancelada,cliente_chegou,no_show,finalizada',
            'observacao' => 'nullable|string|max:500',
            'local' => 'nullable|string|max:100',
            'ocasiao' => 'nullable|string|max:255',
            'cadeiras_extras_utilizadas' => 'nullable|integer|min:0|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $mesaId = $request->get('mesa_id', $reserva->mesa_id);
        $dataReserva = $request->get('data_reserva', $reserva->data_reserva->format('Y-m-d'));
        $horaReserva = $request->get('hora_reserva', $reserva->hora_reserva);
        $mesa = Mesa::findOrFail($mesaId);
        $qtdPessoas = (int) $request->get('qtd_pessoas', $reserva->qtd_pessoas);
        $extrasReq = $request->has('cadeiras_extras_utilizadas')
            ? max(0, (int) $request->input('cadeiras_extras_utilizadas', 0))
            : null;
        $extrasEfetivas = $extrasReq !== null
            ? $extrasReq
            : max(0, $qtdPessoas - $mesa->capacidadeBase());
        $capComExtras = min(99, $mesa->capacidadeBase() + $extrasEfetivas);
        if ($qtdPessoas > $capComExtras) {
            return response()->json([
                'message' => "Com as cadeiras selecionadas a capacidade é {$capComExtras} pessoa(s).",
                'errors' => ['qtd_pessoas' => ['Quantidade excede a capacidade da mesa.']]
            ], 422);
        }

        if ($mesaId != $reserva->mesa_id || $dataReserva != $reserva->data_reserva->format('Y-m-d') || $horaReserva != $reserva->hora_reserva) {
            $conflito = ReservaMesa::where('mesa_id', $mesaId)
                ->where('data_reserva', $dataReserva)
                ->where('hora_reserva', is_string($horaReserva) ? $horaReserva : substr($horaReserva, 0, 5))
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
                ->exists();
            if ($conflito) {
                return response()->json([
                    'message' => 'Já existe uma reserva para esta mesa no mesmo horário.',
                    'errors' => ['mesa_id' => ['Conflito de horário.']]
                ], 422);
            }

            $mesaAntiga = Mesa::find($reserva->mesa_id);
            if ($mesaAntiga) {
                $outrasReservas = ReservaMesa::where('mesa_id', $mesaAntiga->id)
                    ->where('id', '!=', $id)
                    ->where('data_reserva', $reserva->data_reserva)
                    ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
                    ->exists();
                if (!$outrasReservas) {
                    $mesaAntiga->update(['status' => Mesa::STATUS_LIVRE]);
                }
            }

            $mesa->update(['status' => Mesa::STATUS_RESERVADA]);
        }

        $reserva->update($request->only([
            'mesa_id', 'nome_cliente', 'telefone_cliente', 'data_reserva',
            'hora_reserva', 'qtd_pessoas', 'status', 'observacao', 'local', 'ocasiao'
        ]));

        $this->sincronizarVinculoPrincipal($reserva->fresh(), $extrasEfetivas);
        $reserva->load(['mesa:id,numero_mesa,nome_mesa,capacidade,capacidade_base,capacidade_maxima,permite_cadeiras_extras,cadeiras_extras_max', 'usuario:id,nome']);
        $this->anexarMesasCompostas($reserva);
        return response()->json(['message' => 'Reserva atualizada', 'reserva' => $reserva]);
    }

    public function cancelar($id)
    {
        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = request()->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $reserva->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para cancelar esta reserva.'], 403);
        }

        $reserva->update(['status' => ReservaMesa::STATUS_CANCELADA]);

        $outrasReservas = ReservaMesa::where('mesa_id', $reserva->mesa_id)
            ->where('data_reserva', $reserva->data_reserva)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->where('id', '!=', $id)
            ->exists();
        if (!$outrasReservas) {
            $reserva->mesa->update(['status' => Mesa::STATUS_LIVRE]);
        }

        return response()->json(['message' => 'Reserva cancelada', 'reserva' => $reserva->fresh(['mesa', 'usuario'])]);
    }

    public function alterarStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pendente,confirmada,cancelada,cliente_chegou,no_show,finalizada',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Status inválido', 'errors' => $validator->errors()], 422);
        }

        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $reserva->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para alterar esta reserva.'], 403);
        }

        $reserva->update(['status' => $request->status]);

        $mesa = $reserva->mesa;
        $outrasReservas = ReservaMesa::where('mesa_id', $mesa->id)
            ->where('data_reserva', $reserva->data_reserva)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->where('id', '!=', $id)
            ->exists();

        if (in_array($request->status, ['cancelada', 'no_show', 'finalizada']) && !$outrasReservas) {
            $mesa->update(['status' => Mesa::STATUS_LIVRE]);
        } elseif (in_array($request->status, ['cliente_chegou'])) {
            $mesa->update(['status' => Mesa::STATUS_AGUARDANDO_CLIENTE]);
        } elseif (in_array($request->status, ['pendente', 'confirmada'])) {
            $mesa->update(['status' => Mesa::STATUS_RESERVADA]);
        }

        return response()->json([
            'message' => 'Status alterado',
            'reserva' => $reserva->fresh(['mesa', 'usuario']),
        ]);
    }

    /**
     * Snapshot fidelidade da reserva (cartão, saldo, programa).
     */
    public function fidelidade(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva] = $ctx;

        $fid = app(ReservaFidelidadeService::class);
        $snap = $fid->snapshot($reserva);
        $recompensas = ($snap['disponivel'] && $snap['programa_ativo'])
            ? $fid->listarRecompensas((int) $reserva->unidade_id)
            : [];
        $vitrine = app(\App\Services\Fidelidade\FidelidadeVitrineLinkService::class)->paraReserva($reserva, $request);

        return response()->json(array_merge($snap, [
            'recompensas' => $recompensas,
            'reserva_id' => (int) $reserva->id,
            'vitrine_fidelidade' => $vitrine,
        ]));
    }

    /**
     * Conta paga: registra valor, cria cartão se necessário e libera o selo.
     */
    public function fidelidadeContaPaga(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva, 'usuario' => $usuario] = $ctx;

        $data = Validator::make($request->all(), [
            'valor_conta' => 'required|numeric|min:0|max:9999999.99',
            'pagamentos' => 'required|array|min:1',
            'pagamentos.*.meio_id' => 'required|integer|min:1',
            'pagamentos.*.valor' => 'required|numeric|min:0.01|max:9999999.99',
            'pagamentos.*.rotulo' => 'nullable|string|max:80',
        ])->validate();

        try {
            $result = app(ReservaFidelidadeService::class)->registrarContaPaga(
                $reserva,
                (float) $data['valor_conta'],
                $data['pagamentos'],
                $usuario ? (int) $usuario->id : null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Não foi possível registrar a conta paga.',
                'errors' => $e->errors(),
            ], 422);
        }

        $msg = $result['replayed']
            ? 'Conta já estava paga.'.($result['conta'] ? ' Selo desta reserva já havia sido creditado.' : '')
            : ((bool) ($result['reserva']->participa_fidelidade ?? false)
                ? ($result['criado_conta']
                    ? 'Conta paga registrada. Cartão criado e selo liberado.'
                    : 'Conta paga registrada. Selo liberado.')
                : 'Conta paga registrada. Você já pode liberar a mesa.');

        return response()->json([
            'message' => $msg,
            'reserva' => $result['reserva'],
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
            'criado_conta' => $result['criado_conta'],
            'vitrine_fidelidade' => app(\App\Services\Fidelidade\FidelidadeVitrineLinkService::class)
                ->paraReserva($result['reserva'], $request),
        ], $result['replayed'] ? 200 : 201);
    }

    /**
     * Credita selo manualmente pela reserva.
     */
    public function fidelidadeSelo(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva, 'usuario' => $usuario] = $ctx;

        try {
            $result = app(ReservaFidelidadeService::class)->creditarSelo(
                $reserva,
                $usuario ? (int) $usuario->id : null,
                true
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Não foi possível creditar o selo.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => $result['replayed'] ? 'Selo já havia sido creditado nesta reserva.' : 'Selo creditado.',
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
            'criado_conta' => $result['criado_conta'],
        ], $result['replayed'] ? 200 : 201);
    }

    /**
     * Marca se o cliente deseja participar do programa fidelidade nesta reserva.
     */
    public function participaFidelidade(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva] = $ctx;

        if ($reserva->conta_paga) {
            return response()->json(['message' => 'Conta já paga; não é possível alterar a opção de fidelidade.'], 422);
        }

        $data = Validator::make($request->all(), [
            'participa_fidelidade' => 'required|boolean',
        ])->validate();

        $participa = (bool) $data['participa_fidelidade'];

        if (! $participa) {
            $reserva->participa_fidelidade = false;
            app(ReservaFidelidadeService::class)->limparDadosFidelidade($reserva);
        } else {
            $reserva->participa_fidelidade = true;
            $reserva->save();
        }

        return response()->json([
            'message' => $reserva->participa_fidelidade
                ? 'Cliente participará do fidelidade. Informe nome, CPF e e-mail antes de liberar o selo.'
                : 'Cliente não participará do fidelidade. Registre apenas valor e pagamento.',
            'reserva' => $reserva->fresh(['mesa', 'usuario']),
        ]);
    }

    /**
     * Salva nome, CPF e e-mail do cliente para o cartão fidelidade (com validação de unicidade).
     */
    public function fidelidadeDados(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva] = $ctx;

        if ($reserva->conta_paga) {
            return response()->json(['message' => 'Conta já paga; não é possível alterar os dados de fidelidade.'], 422);
        }

        $data = Validator::make($request->all(), [
            'fidelidade_nome' => 'required|string|min:3|max:160',
            'fidelidade_cpf' => 'required|string|max:20',
            'fidelidade_email' => 'required|email|max:160',
        ])->validate();

        try {
            app(ReservaFidelidadeService::class)->salvarDadosFidelidade(
                $reserva,
                $data['fidelidade_nome'],
                $data['fidelidade_cpf'],
                $data['fidelidade_email']
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Não foi possível validar os dados.',
                'errors' => $e->errors(),
            ], 422);
        }

        $reserva->participa_fidelidade = true;
        $reserva->save();

        return response()->json([
            'message' => 'Dados do cartão fidelidade confirmados.',
            'reserva' => $reserva->fresh(['mesa', 'usuario']),
        ]);
    }

    /**
     * Garante cartão fidelidade pelo telefone da reserva (sem creditar selo).
     */
    public function fidelidadeGarantir(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva, 'usuario' => $usuario] = $ctx;

        try {
            $conta = app(ReservaFidelidadeService::class)->garantirConta(
                $reserva,
                $usuario ? (int) $usuario->id : null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Não foi possível criar o cartão.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cartão fidelidade pronto.',
            'conta' => $conta,
        ]);
    }

    /**
     * Paga / resgata recompensa com selos na reserva.
     */
    public function fidelidadeResgatar(Request $request, $id)
    {
        $ctx = $this->autorizarReservaOu403($request, $id);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['reserva' => $reserva, 'usuario' => $usuario] = $ctx;

        $data = Validator::make($request->all(), [
            'recompensa_id' => 'nullable|integer',
            'observacao' => 'nullable|string|max:500',
        ])->validate();

        try {
            $result = app(ReservaFidelidadeService::class)->pagarComSelos(
                $reserva,
                isset($data['recompensa_id']) ? (int) $data['recompensa_id'] : null,
                $usuario ? (int) $usuario->id : null,
                $data['observacao'] ?? null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Não foi possível resgatar.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Resgate realizado com selos.',
            'resgate' => $result['resgate'],
            'conta' => $result['conta'],
            'ledger' => $result['ledger'],
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? 200 : 201);
    }

    /**
     * @return array{reserva:ReservaMesa,usuario:?object}|\Illuminate\Http\JsonResponse
     */
    protected function autorizarReservaOu403(Request $request, $id)
    {
        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->first() : null;

        if (! ReservaMesaAcesso::temAcessoModulo($usuario)) {
            return response()->json(['message' => 'Sem permissão para reservas.'], 403);
        }
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $reserva->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para esta reserva.'], 403);
        }

        return ['reserva' => $reserva, 'usuario' => $usuario];
    }

    public function destroy(Request $request, $id)
    {
        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->first() : null;

        if (! ReservaMesaAcesso::temAcessoModulo($usuario)) {
            return response()->json(['message' => 'Sem permissão para excluir reservas.'], 403);
        }

        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $reserva->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para excluir esta reserva.'], 403);
        }

        $mesaId = (int) $reserva->mesa_id;
        $dataReserva = $reserva->data_reserva;

        DB::transaction(function () use ($reserva) {
            if (Schema::hasTable('reserva_mesas')) {
                DB::table('reserva_mesas')->where('reserva_id', $reserva->id)->delete();
            }
            $reserva->delete();
        });

        $outrasReservas = ReservaMesa::where('mesa_id', $mesaId)
            ->where('data_reserva', $dataReserva)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->exists();

        if (! $outrasReservas) {
            Mesa::where('id', $mesaId)->update(['status' => Mesa::STATUS_LIVRE]);
        }

        return response()->json(['message' => 'Reserva excluída definitivamente do histórico.']);
    }

    /**
     * Histórico de reservas com contagem por cliente.
     * Filtros: unidade_id, data_inicio, data_fim, status.
     */
    public function historico(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }
        $unidadeId = $this->resolveUnidadeId($request, $usuario);

        if (!$unidadeId) {
            return response()->json([]);
        }

        $query = ReservaMesa::with(['mesa:id,numero_mesa,nome_mesa', 'unidade:id,nome'])
            ->where('unidade_id', $unidadeId);

        if ($request->filled('data_inicio')) {
            $query->where('data_reserva', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->where('data_reserva', '<=', $request->data_fim);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reservas = $query->orderBy('data_reserva', 'desc')
            ->orderBy('hora_reserva', 'desc')
            ->get();

        $chaveCliente = function ($r) {
            $tel = preg_replace('/\D/', '', $r->telefone_cliente ?? '');
            return $tel ? $tel : ($r->nome_cliente ?? '');
        };

        $contagemPorCliente = [];
        foreach ($reservas as $r) {
            $key = $chaveCliente($r);
            $contagemPorCliente[$key] = ($contagemPorCliente[$key] ?? 0) + 1;
        }

        $result = $reservas->map(function ($r) use ($chaveCliente, $contagemPorCliente) {
            $key = $chaveCliente($r);
            return [
                'id' => $r->id,
                'nome_cliente' => $r->nome_cliente,
                'telefone_cliente' => $r->telefone_cliente,
                'qtd_pessoas' => $r->qtd_pessoas,
                'data_reserva' => $r->data_reserva,
                'hora_reserva' => $r->hora_reserva,
                'status' => $r->status,
                'mesa' => $r->mesa,
                'unidade' => $r->unidade,
                'total_reservas_cliente' => $contagemPorCliente[$key] ?? 1,
            ];
        });

        return response()->json($result->values()->all());
    }

    /** Mantém pivô alinhado à mesa principal (compatibilidade com composição Ayla). */
    private function sincronizarVinculoPrincipal(ReservaMesa $reserva, ?int $cadeirasExtras = null): void
    {
        if (! Schema::hasTable('reserva_mesas') || ! $reserva->id || ! $reserva->mesa_id) {
            return;
        }

        $mesa = Mesa::find($reserva->mesa_id);
        $base = $mesa ? $mesa->capacidadeBase() : 0;
        if ($cadeirasExtras === null) {
            $cadeirasExtras = max(0, (int) $reserva->qtd_pessoas - $base);
        }
        $cadeirasExtras = max(0, min(99, (int) $cadeirasExtras));

        $now = now();
        $exists = DB::table('reserva_mesas')
            ->where('reserva_id', $reserva->id)
            ->where('principal', true)
            ->exists();

        if ($exists) {
            DB::table('reserva_mesas')
                ->where('reserva_id', $reserva->id)
                ->where('principal', true)
                ->update([
                    'mesa_id' => (int) $reserva->mesa_id,
                    'capacidade_utilizada' => (int) $reserva->qtd_pessoas,
                    'cadeiras_extras_utilizadas' => $cadeirasExtras,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('reserva_mesas')->updateOrInsert(
            ['reserva_id' => (int) $reserva->id, 'mesa_id' => (int) $reserva->mesa_id],
            [
                'capacidade_utilizada' => (int) $reserva->qtd_pessoas,
                'cadeiras_extras_utilizadas' => $cadeirasExtras,
                'principal' => true,
                'configuracao_emergencial' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function anexarMesasCompostas(ReservaMesa $reserva): void
    {
        if (! Schema::hasTable('reserva_mesas')) {
            $reserva->setAttribute('mesas_vinculadas', []);
            $reserva->setAttribute('alerta_preparo_fisico', false);

            return;
        }

        $vinculos = DB::table('reserva_mesas as rm')
            ->leftJoin('mesas as m', 'm.id', '=', 'rm.mesa_id')
            ->where('rm.reserva_id', $reserva->id)
            ->orderByDesc('rm.principal')
            ->get([
                'rm.mesa_id', 'rm.capacidade_utilizada', 'rm.cadeiras_extras_utilizadas',
                'rm.principal', 'rm.configuracao_emergencial',
                'm.numero_mesa', 'm.nome_mesa', 'm.capacidade', 'm.capacidade_base', 'm.capacidade_maxima',
            ])
            ->map(fn ($r) => [
                'mesa_id' => (int) $r->mesa_id,
                'numero_mesa' => $r->numero_mesa,
                'nome_mesa' => $r->nome_mesa,
                'capacidade_utilizada' => (int) $r->capacidade_utilizada,
                'cadeiras_extras_utilizadas' => (int) $r->cadeiras_extras_utilizadas,
                'principal' => (bool) $r->principal,
                'configuracao_emergencial' => (bool) $r->configuracao_emergencial,
                'capacidade_base' => (int) ($r->capacidade_base ?? $r->capacidade ?? 0),
                'capacidade_maxima' => (int) ($r->capacidade_maxima ?? $r->capacidade ?? 0),
            ])->values()->all();

        $reserva->setAttribute('mesas_vinculadas', $vinculos);
        $composicao = count($vinculos) > 1;
        $extras = collect($vinculos)->sum('cadeiras_extras_utilizadas') > 0;
        $emerg = collect($vinculos)->contains(fn ($v) => ! empty($v['configuracao_emergencial']));
        $reserva->setAttribute('alerta_preparo_fisico', $composicao || $extras || $emerg);
        $reserva->setAttribute('composicao', $composicao);
    }

    public function meiosPagamentoIndex(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        if (! $unidadeId) {
            return response()->json(['message' => 'Informe a unidade.'], 422);
        }

        $svc = app(ReservaMeioPagamentoService::class);

        return response()->json([
            'items' => $svc->listarAdmin($unidadeId),
            'agrupados' => $svc->agrupadosPorUnidade($unidadeId, false),
            'tipos' => \App\Models\ReservaMeioPagamento::TIPOS,
        ]);
    }

    public function meiosPagamentoStore(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        if (! $unidadeId) {
            return response()->json(['message' => 'Informe a unidade.'], 422);
        }

        $data = Validator::make($request->all(), [
            'tipo' => 'required|string|max:20',
            'nome' => 'required|string|max:160',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0|max:9999',
        ])->validate();

        try {
            $item = app(ReservaMeioPagamentoService::class)->criar($unidadeId, $data);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['message' => 'Meio de pagamento cadastrado.', 'item' => $item], 201);
    }

    public function meiosPagamentoUpdate(Request $request, $id)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        if (! $unidadeId) {
            return response()->json(['message' => 'Informe a unidade.'], 422);
        }

        $data = Validator::make($request->all(), [
            'tipo' => 'sometimes|string|max:20',
            'nome' => 'sometimes|string|max:160',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0|max:9999',
        ])->validate();

        try {
            $item = app(ReservaMeioPagamentoService::class)->atualizar($unidadeId, (int) $id, $data);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['message' => 'Meio de pagamento atualizado.', 'item' => $item]);
    }

    public function meiosPagamentoDestroy(Request $request, $id)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        if ($resp = $this->assertUnidadeDoUsuarioOu403($request, $usuario)) {
            return $resp;
        }

        $unidadeId = $this->resolveUnidadeId($request, $usuario);
        if (! $unidadeId) {
            return response()->json(['message' => 'Informe a unidade.'], 422);
        }

        try {
            app(ReservaMeioPagamentoService::class)->excluir($unidadeId, (int) $id);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['message' => 'Meio de pagamento removido.']);
    }
}
