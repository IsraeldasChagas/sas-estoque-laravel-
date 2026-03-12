<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReservaMesaController extends Controller
{
    public function index(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        $query = ReservaMesa::with(['mesa:id,numero_mesa,nome_mesa,capacidade,unidade_id', 'usuario:id,nome']);

        if ($request->filled('unidade_id')) {
            $query->where('unidade_id', $request->unidade_id);
        } elseif ($perfil !== 'ADMIN' && $unidadeIdUsuario) {
            $query->where('unidade_id', $unidadeIdUsuario);
        }

        if ($request->filled('data_reserva')) {
            $query->where('data_reserva', $request->data_reserva);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        $unidadeId = $request->get('unidade_id');
        $dataReserva = $request->get('data_reserva', date('Y-m-d'));

        $queryMesas = Mesa::where('ativo', true);
        if ($unidadeId) {
            $queryMesas->where('unidade_id', $unidadeId);
        } elseif ($perfil !== 'ADMIN' && $unidadeIdUsuario) {
            $queryMesas->where('unidade_id', $unidadeIdUsuario);
        }
        $totalMesas = $queryMesas->count();

        $queryReservas = ReservaMesa::where('data_reserva', $dataReserva)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada']);
        if ($unidadeId) {
            $queryReservas->where('unidade_id', $unidadeId);
        } elseif ($perfil !== 'ADMIN' && $unidadeIdUsuario) {
            $queryReservas->where('unidade_id', $unidadeIdUsuario);
        }
        $reservasAtivas = $queryReservas->get();

        $mesasIdsComReserva = $reservasAtivas->pluck('mesa_id')->unique();
        $livres = $totalMesas - $mesasIdsComReserva->count();
        $reservadas = $reservasAtivas->whereIn('status', ['pendente', 'confirmada'])->count();
        $ocupadas = $reservasAtivas->whereIn('status', ['cliente_chegou'])->count();
        $aguardando = $reservasAtivas->where('status', 'cliente_chegou')->count();

        return response()->json([
            'total_mesas' => $totalMesas,
            'mesas_livres' => max(0, $livres),
            'mesas_reservadas' => $reservadas,
            'mesas_ocupadas' => $ocupadas,
            'mesas_aguardando_cliente' => $aguardando,
            'total_reservas_dia' => $reservasAtivas->count(),
        ]);
    }

    public function store(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        if ($perfil !== 'ADMIN' && $unidadeIdUsuario) {
            $request->merge(['unidade_id' => $unidadeIdUsuario]);
        }

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
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $mesa = Mesa::findOrFail($request->mesa_id);
        if ($mesa->unidade_id != $request->unidade_id) {
            return response()->json(['message' => 'Mesa não pertence à unidade selecionada.'], 422);
        }
        if (!$mesa->ativo) {
            return response()->json(['message' => 'Mesa está inativa ou bloqueada.'], 422);
        }
        if ($mesa->status === Mesa::STATUS_BLOQUEADA) {
            return response()->json(['message' => 'Mesa está bloqueada para reservas.'], 422);
        }
        if ($request->qtd_pessoas > $mesa->capacidade) {
            return response()->json([
                'message' => "A mesa suporta no máximo {$mesa->capacidade} pessoas.",
                'errors' => ['qtd_pessoas' => ['Quantidade excede a capacidade da mesa.']]
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

        $reserva->load(['mesa:id,numero_mesa,nome_mesa,capacidade', 'usuario:id,nome']);
        return response()->json(['message' => 'Reserva criada com sucesso', 'reserva' => $reserva], 201);
    }

    public function show($id)
    {
        $reserva = ReservaMesa::with(['mesa:id,numero_mesa,nome_mesa,capacidade,localizacao', 'usuario:id,nome', 'unidade:id,nome'])
            ->findOrFail($id);
        return response()->json($reserva);
    }

    public function update(Request $request, $id)
    {
        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        if ($perfil !== 'ADMIN' && $unidadeIdUsuario && $reserva->unidade_id != $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para editar esta reserva.'], 403);
        }

        if (in_array($reserva->status, [ReservaMesa::STATUS_CANCELADA, ReservaMesa::STATUS_FINALIZADA, ReservaMesa::STATUS_NO_SHOW])) {
            return response()->json(['message' => 'Reserva não pode ser editada neste status.'], 422);
        }

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
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $mesaId = $request->get('mesa_id', $reserva->mesa_id);
        $dataReserva = $request->get('data_reserva', $reserva->data_reserva->format('Y-m-d'));
        $horaReserva = $request->get('hora_reserva', $reserva->hora_reserva);

        if ($mesaId != $reserva->mesa_id || $dataReserva != $reserva->data_reserva->format('Y-m-d') || $horaReserva != $reserva->hora_reserva) {
            $mesa = Mesa::findOrFail($mesaId);
            if ($request->has('qtd_pessoas') && $request->qtd_pessoas > $mesa->capacidade) {
                return response()->json([
                    'message' => "A mesa suporta no máximo {$mesa->capacidade} pessoas.",
                    'errors' => ['qtd_pessoas' => ['Quantidade excede a capacidade da mesa.']]
                ], 422);
            }
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
        } elseif ($request->has('qtd_pessoas')) {
            $mesa = Mesa::find($mesaId);
            if ($request->qtd_pessoas > $mesa->capacidade) {
                return response()->json([
                    'message' => "A mesa suporta no máximo {$mesa->capacidade} pessoas.",
                    'errors' => ['qtd_pessoas' => ['Quantidade excede a capacidade da mesa.']]
                ], 422);
            }
        }

        $reserva->update($request->only([
            'mesa_id', 'nome_cliente', 'telefone_cliente', 'data_reserva',
            'hora_reserva', 'qtd_pessoas', 'status', 'observacao', 'local', 'ocasiao'
        ]));

        $reserva->load(['mesa:id,numero_mesa,nome_mesa,capacidade', 'usuario:id,nome']);
        return response()->json(['message' => 'Reserva atualizada', 'reserva' => $reserva]);
    }

    public function cancelar($id)
    {
        $reserva = ReservaMesa::findOrFail($id);
        $usuarioId = request()->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        if ($perfil !== 'ADMIN' && $unidadeIdUsuario && $reserva->unidade_id != $unidadeIdUsuario) {
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
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        if ($perfil !== 'ADMIN' && $unidadeIdUsuario && $reserva->unidade_id != $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para alterar esta reserva.'], 403);
        }

        $statusAnterior = $reserva->status;
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

        return response()->json(['message' => 'Status alterado', 'reserva' => $reserva->fresh(['mesa', 'usuario'])]);
    }

    public function destroy($id)
    {
        return $this->cancelar($id);
    }
}
