<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\ReservaMesa;
use App\Support\ReservaMesaAcesso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class MesaController extends Controller
{
    protected function podeGerenciarTodasUnidades(?object $usuario): bool
    {
        return ReservaMesaAcesso::podeGerenciarTodasUnidades($usuario);
    }

    public function index(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        $query = Mesa::query()->where('ativo', true);

        $unidadeId = null;
        if (! $this->podeGerenciarTodasUnidades($usuario)) {
            $unidadeId = $unidadeIdUsuario > 0 ? $unidadeIdUsuario : null;
        } else {
            if ($request->filled('unidade_id')) {
                $unidadeId = (int) $request->unidade_id;
            } elseif ($unidadeIdUsuario) {
                $unidadeId = (int) $unidadeIdUsuario;
            }
        }
        if ($unidadeId <= 0) {
            return response()->json([]);
        }
        $query->where('unidade_id', $unidadeId);

        $mesas = $this->ordenarMesasPorNumero($query->get());
        return response()->json($mesas);
    }

    public function store(Request $request)
    {
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;

        $unidadeId = null;
        if (! $this->podeGerenciarTodasUnidades($usuario)) {
            if ($unidadeIdUsuario <= 0) {
                return response()->json(['message' => 'Usuário sem unidade cadastrada.'], 403);
            }
            if ($request->filled('unidade_id') && (int) $request->unidade_id !== $unidadeIdUsuario) {
                return response()->json(['message' => 'Sem permissão para criar mesa em outra unidade.'], 403);
            }
            $unidadeId = $unidadeIdUsuario;
        } else {
            if ($request->filled('unidade_id')) {
                $unidadeId = (int) $request->unidade_id;
            } elseif ($unidadeIdUsuario) {
                $unidadeId = (int) $unidadeIdUsuario;
            }
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
            'capacidade_base' => 'nullable|integer|min:1|max:99',
            'permite_cadeiras_extras' => 'nullable|boolean',
            'cadeiras_extras_max' => 'nullable|integer|min:0|max:50',
            'capacidade_maxima' => 'nullable|integer|min:1|max:149',
            'localizacao' => 'nullable|string|max:100',
            'pode_juntar' => 'nullable|boolean',
            'pode_separar' => 'nullable|boolean',
            'grupo_composicao' => 'nullable|string|max:100',
            'status' => 'nullable|in:livre,reservada,aguardando_cliente,ocupada,bloqueada',
            'observacao' => 'nullable|string|max:500',
            'cadastro_emergencial' => 'nullable|boolean',
            'motivo_cadastro' => 'nullable|string|max:255',
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

        $base = (int) ($request->input('capacidade_base', $request->capacidade));
        $extras = (int) ($request->input('cadeiras_extras_max', 0));
        $maxima = (int) ($request->input('capacidade_maxima', $base + $extras));
        if ($base < 1) {
            return response()->json(['message' => 'Capacidade base deve ser >= 1.'], 422);
        }
        if ($extras < 0 || $maxima < $base) {
            return response()->json(['message' => 'Capacidade máxima inválida.'], 422);
        }

        $attrs = $request->only([
            'unidade_id', 'numero_mesa', 'nome_mesa', 'capacidade',
            'localizacao', 'pode_juntar', 'pode_separar', 'status', 'observacao'
        ]);
        $attrs['capacidade'] = $base;
        if (Schema::hasColumn('mesas', 'capacidade_base')) {
            $attrs['capacidade_base'] = $base;
            $attrs['permite_cadeiras_extras'] = $request->boolean('permite_cadeiras_extras') || $extras > 0;
            $attrs['cadeiras_extras_max'] = $extras;
            $attrs['capacidade_maxima'] = $maxima;
            $attrs['grupo_composicao'] = $request->input('grupo_composicao');
            $attrs['cadastro_emergencial'] = $request->boolean('cadastro_emergencial');
            $attrs['motivo_cadastro'] = $request->input('motivo_cadastro');
        }

        $mesa = Mesa::create($attrs);

        return response()->json(['message' => 'Mesa criada com sucesso', 'mesa' => $mesa], 201);
    }

    public function show($id)
    {
        $mesa = Mesa::with('unidade:id,nome')->findOrFail($id);
        $usuarioId = request()->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $mesa->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para acessar esta mesa.'], 403);
        }
        return response()->json($mesa);
    }

    public function update(Request $request, $id)
    {
        $mesa = Mesa::findOrFail($id);
        $usuarioId = $request->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $mesa->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para editar mesa de outra unidade.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'numero_mesa' => 'sometimes|required|string|max:50',
            'nome_mesa' => 'nullable|string|max:255',
            'capacidade' => 'sometimes|required|integer|min:1|max:99',
            'capacidade_base' => 'nullable|integer|min:1|max:99',
            'permite_cadeiras_extras' => 'nullable|boolean',
            'cadeiras_extras_max' => 'nullable|integer|min:0|max:50',
            'capacidade_maxima' => 'nullable|integer|min:1|max:149',
            'localizacao' => 'nullable|string|max:100',
            'pode_juntar' => 'nullable|boolean',
            'pode_separar' => 'nullable|boolean',
            'grupo_composicao' => 'nullable|string|max:100',
            'status' => 'nullable|in:livre,reservada,aguardando_cliente,ocupada,bloqueada',
            'observacao' => 'nullable|string|max:500',
            'ativo' => 'nullable|boolean',
            'cadastro_emergencial' => 'nullable|boolean',
            'motivo_cadastro' => 'nullable|string|max:255',
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

        $attrs = $request->only([
            'numero_mesa', 'nome_mesa', 'capacidade', 'localizacao',
            'pode_juntar', 'pode_separar', 'status', 'observacao', 'ativo'
        ]);

        if (Schema::hasColumn('mesas', 'capacidade_base')) {
            if ($request->filled('capacidade_base') || $request->filled('capacidade')) {
                $base = (int) $request->input('capacidade_base', $request->input('capacidade', $mesa->capacidadeBase()));
                $attrs['capacidade'] = $base;
                $attrs['capacidade_base'] = $base;
            }
            if ($request->has('permite_cadeiras_extras') || $request->has('cadeiras_extras_max') || $request->has('capacidade_maxima')) {
                $base = (int) ($attrs['capacidade_base'] ?? $mesa->capacidadeBase());
                $extras = (int) $request->input('cadeiras_extras_max', $mesa->cadeiras_extras_max ?? 0);
                $maxima = (int) $request->input('capacidade_maxima', $base + $extras);
                $attrs['permite_cadeiras_extras'] = $request->has('permite_cadeiras_extras')
                    ? $request->boolean('permite_cadeiras_extras')
                    : ($extras > 0);
                $attrs['cadeiras_extras_max'] = $extras;
                $attrs['capacidade_maxima'] = max($base, $maxima);
            }
            foreach (['grupo_composicao', 'cadastro_emergencial', 'motivo_cadastro'] as $campo) {
                if ($request->exists($campo)) {
                    $attrs[$campo] = $request->input($campo);
                }
            }
        }

        $mesa->update($attrs);

        return response()->json(['message' => 'Mesa atualizada', 'mesa' => $mesa->fresh()]);
    }

    public function destroy($id)
    {
        $mesa = Mesa::findOrFail($id);
        $usuarioId = request()->header('X-Usuario-Id');
        $usuario = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->first() : null;
        $unidadeIdUsuario = $usuario ? (int) ($usuario->unidade_id ?? 0) : 0;
        if (! $this->podeGerenciarTodasUnidades($usuario) && $unidadeIdUsuario > 0 && (int) $mesa->unidade_id !== $unidadeIdUsuario) {
            return response()->json(['message' => 'Sem permissão para excluir mesa de outra unidade.'], 403);
        }
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

    private function ordenarMesasPorNumero($mesas)
    {
        return $mesas->sortBy(function ($mesa) {
            return $this->numeroMesaParaInt($mesa->numero_mesa ?? '');
        })->values();
    }

    private function numeroMesaParaInt($numero): int
    {
        return (int) preg_replace('/\D/', '', (string) $numero);
    }
}
