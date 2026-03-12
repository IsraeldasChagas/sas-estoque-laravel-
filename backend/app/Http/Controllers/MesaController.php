<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MesaController extends Controller
{
    public function index(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        $query = Mesa::with('unidade:id,nome')->where('ativo', true);

        if ($perfil === 'ADMIN') {
            if ($request->filled('unidade_id')) {
                $query->where('unidade_id', $request->unidade_id);
            }
        } else {
            $query->where('unidade_id', $unidadeIdUsuario);
        }

        $mesas = $query->orderBy('numero_mesa')->get();
        return response()->json($mesas);
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
            'numero_mesa' => 'required|string|max:50',
            'nome_mesa' => 'nullable|string|max:255',
            'capacidade' => 'required|integer|min:1|max:99',
            'localizacao' => 'nullable|string|max:100',
            'pode_juntar' => 'nullable|boolean',
            'pode_separar' => 'nullable|boolean',
            'status' => 'nullable|in:livre,reservada,aguardando_cliente,ocupada,bloqueada',
            'observacao' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        $existe = Mesa::where('unidade_id', $request->unidade_id)
            ->where('numero_mesa', $request->numero_mesa)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Já existe uma mesa com esse número nesta unidade.',
                'errors' => ['numero_mesa' => ['Número de mesa já existe na unidade.']]
            ], 422);
        }

        $mesa = Mesa::create($request->only([
            'unidade_id', 'numero_mesa', 'nome_mesa', 'capacidade',
            'localizacao', 'pode_juntar', 'pode_separar', 'status', 'observacao'
        ]));

        return response()->json(['message' => 'Mesa criada com sucesso', 'mesa' => $mesa], 201);
    }

    public function show($id)
    {
        $mesa = Mesa::with('unidade:id,nome')->findOrFail($id);
        return response()->json($mesa);
    }

    public function update(Request $request, $id)
    {
        $mesa = Mesa::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'numero_mesa' => 'sometimes|required|string|max:50',
            'nome_mesa' => 'nullable|string|max:255',
            'capacidade' => 'sometimes|required|integer|min:1|max:99',
            'localizacao' => 'nullable|string|max:100',
            'pode_juntar' => 'nullable|boolean',
            'pode_separar' => 'nullable|boolean',
            'status' => 'nullable|in:livre,reservada,aguardando_cliente,ocupada,bloqueada',
            'observacao' => 'nullable|string|max:500',
            'ativo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos', 'errors' => $validator->errors()], 422);
        }

        if ($request->has('numero_mesa') && $request->numero_mesa !== $mesa->numero_mesa) {
            $existe = Mesa::where('unidade_id', $mesa->unidade_id)
                ->where('numero_mesa', $request->numero_mesa)
                ->where('id', '!=', $id)
                ->exists();
            if ($existe) {
                return response()->json([
                    'message' => 'Já existe uma mesa com esse número nesta unidade.',
                    'errors' => ['numero_mesa' => ['Número de mesa já existe na unidade.']]
                ], 422);
            }
        }

        $mesa->update($request->only([
            'numero_mesa', 'nome_mesa', 'capacidade', 'localizacao',
            'pode_juntar', 'pode_separar', 'status', 'observacao', 'ativo'
        ]));

        return response()->json(['message' => 'Mesa atualizada', 'mesa' => $mesa]);
    }

    public function destroy($id)
    {
        $mesa = Mesa::findOrFail($id);
        $temReservaAtiva = ReservaMesa::where('mesa_id', $id)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->exists();
        $estaLivre = ($mesa->status ?? '') === Mesa::STATUS_LIVRE;
        if ($estaLivre && !$temReservaAtiva) {
            $mesa->delete();
            return response()->json(['message' => 'Mesa excluída com sucesso']);
        }
        $mesa->ativo = false;
        $mesa->save();
        return response()->json(['message' => 'Mesa inativada (tinha reservas ou não estava livre)']);
    }

    public function inativar($id)
    {
        return $this->destroy($id);
    }
}
