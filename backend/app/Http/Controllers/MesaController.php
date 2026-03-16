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

        $query = Mesa::query()->where('ativo', true);

        // Isolar mesas por unidade, mesma regra das reservas:
        // - se vier unidade_id no request, usamos essa (para qualquer perfil);
        // - senão, se o usuário tiver unidade fixa, usamos a dele;
        // - se mesmo assim não tiver unidade, não retornamos mesas.
        $unidadeId = null;
        if ($request->filled('unidade_id')) {
            $unidadeId = (int) $request->unidade_id;
        } elseif ($unidadeIdUsuario) {
            $unidadeId = (int) $unidadeIdUsuario;
        }
        if ($unidadeId <= 0) {
            return response()->json([]);
        }
        $query->where('unidade_id', $unidadeId);

        $mesas = $query->orderBy('numero_mesa')->get();
        return response()->json($mesas);
    }

    public function store(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $perfil = $usuario ? strtoupper(trim($usuario->perfil ?? '')) : '';
        $unidadeIdUsuario = $usuario ? $usuario->unidade_id : null;

        // Definição da unidade da mesa:
        // - Se vier unidade_id no request, usamos essa;
        // - Senão, se o usuário tiver unidade fixa, usamos a dele;
        // - Se não houver unidade válida, não permitimos criar.
        $unidadeId = null;
        if ($request->filled('unidade_id')) {
            $unidadeId = (int) $request->unidade_id;
        } elseif ($unidadeIdUsuario) {
            $unidadeId = (int) $unidadeIdUsuario;
        }
        if ($unidadeId <= 0 || !DB::table('unidades')->where('id', $unidadeId)->exists()) {
            return response()->json(['message' => 'Unidade inválida ou não informada.'], 422);
        }
        $request->merge(['unidade_id' => $unidadeId]);

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
            ->where('ativo', true)
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
        $estaOcupada = ($mesa->status ?? '') === Mesa::STATUS_OCUPADA;

        if ($estaOcupada) {
            $mesa->ativo = false;
            $mesa->save();
            return response()->json([
                'message' => 'Mesa ocupada. Apenas inativada. Exclua depois que estiver livre.',
            ]);
        }

        // Mesa não ocupada: cancela reservas ativas vinculadas e exclui
        ReservaMesa::where('mesa_id', $id)
            ->whereNotIn('status', ['cancelada', 'no_show', 'finalizada'])
            ->update(['status' => 'cancelada']);

        $mesa->delete();
        return response()->json(['message' => 'Mesa excluída com sucesso']);
    }

    public function inativar($id)
    {
        return $this->destroy($id);
    }
}
