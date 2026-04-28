<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Support\Rh\RhAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RhEntrevistaController extends Controller
{
    public function index(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $q = DB::table('rh_entrevistas')
            ->join('rh_candidatos', 'rh_entrevistas.candidato_id', '=', 'rh_candidatos.id')
            ->leftJoin('rh_vagas', 'rh_candidatos.vaga_id', '=', 'rh_vagas.id')
            ->select('rh_entrevistas.*', 'rh_candidatos.nome as candidato_nome', 'rh_vagas.titulo as vaga_titulo')
            ->orderByDesc('rh_entrevistas.id');

        if ($request->filled('status')) $q->where('rh_entrevistas.status', $request->status);
        if ($request->filled('candidato_id')) $q->where('rh_entrevistas.candidato_id', (int) $request->candidato_id);
        if ($request->filled('data')) $q->where('rh_entrevistas.data', $request->data);

        return response()->json($q->get())->header('Access-Control-Allow-Origin', '*');
    }

    public function store(Request $request)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $request->validate([
            'candidato_id' => 'required|integer',
            'data' => 'nullable|date',
            'hora' => 'nullable|date_format:H:i',
            'local' => 'nullable|string|max:160',
            'responsavel' => 'nullable|string|max:160',
            'observacao' => 'nullable|string|max:20000',
            'status' => 'nullable|string|in:agendada,realizada,cancelada',
        ]);

        if (! DB::table('rh_candidatos')->where('id', $data['candidato_id'])->exists()) {
            return response()->json(['error' => 'Candidato não encontrado'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $id = DB::table('rh_entrevistas')->insertGetId([
            'candidato_id' => (int) $data['candidato_id'],
            'data' => $data['data'] ?? null,
            'hora' => $data['hora'] ?? null,
            'local' => $data['local'] ?? null,
            'responsavel' => $data['responsavel'] ?? null,
            'observacao' => $data['observacao'] ?? null,
            'status' => $data['status'] ?? 'agendada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('rh_entrevistas')->where('id', $id)->first())
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function update(Request $request, int $id)
    {
        if (! RhAcesso::pode($request, 'rh.candidatos')) {
            return response()->json(['error' => 'Sem permissão.'], 403)->header('Access-Control-Allow-Origin', '*');
        }

        if (! DB::table('rh_entrevistas')->where('id', $id)->exists()) {
            return response()->json(['error' => 'Entrevista não encontrada'], 404)->header('Access-Control-Allow-Origin', '*');
        }

        $data = $request->validate([
            'data' => 'nullable|date',
            'hora' => 'nullable|date_format:H:i',
            'local' => 'nullable|string|max:160',
            'responsavel' => 'nullable|string|max:160',
            'observacao' => 'nullable|string|max:20000',
            'status' => 'nullable|string|in:agendada,realizada,cancelada',
        ]);

        DB::table('rh_entrevistas')->where('id', $id)->update([
            ...$data,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('rh_entrevistas')->where('id', $id)->first())
            ->header('Access-Control-Allow-Origin', '*');
    }
}

