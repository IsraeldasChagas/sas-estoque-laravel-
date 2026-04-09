<?php

namespace App\Http\Controllers;

use App\Models\Alvara;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlvaraController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Alvara::query();

            if ($request->filled('unidade_id')) {
                $query->where('unidade_id', $request->unidade_id);
            }

            // Filtro por mes/ano baseado na data de vencimento
            if ($request->filled('mes_ano')) {
                $mesAno = explode('-', $request->mes_ano);
                if (count($mesAno) === 2) {
                    $ano = $mesAno[0];
                    $mes = $mesAno[1];
                    $query->whereYear('data_vencimento', $ano)
                          ->whereMonth('data_vencimento', $mes);
                }
            }

            $alvaras = $query->orderBy('data_vencimento', 'desc')->get();
            return response()->json($alvaras);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar alvarás',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Normaliza campos vazios antes da validação
        $input = $request->all();
        if (isset($input['unidade_id']) && $input['unidade_id'] === '') {
            $request->merge(['unidade_id' => null]);
        }

        $validator = Validator::make($request->all(), [
            'unidade_id' => 'nullable|exists:unidades,id',
            'tipo' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_vencimento' => 'required|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'anexo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();

            $usuarioId = $request->header('X-Usuario-Id');
            if ($usuarioId) {
                $data['usuario_id'] = $usuarioId;
            }

            if ($request->hasFile('anexo')) {
                $file = $request->file('anexo');
                $nomeOriginal = $file->getClientOriginalName();
                $extensao = $file->getClientOriginalExtension();
                $nomeArquivo = time() . '_' . uniqid() . '.' . $extensao;
                $path = $file->storeAs('alvaras', $nomeArquivo, 'public');
                $data['anexo_path'] = $path;
                $data['anexo_nome'] = $nomeOriginal;
                $data['anexo_tipo'] = $extensao;
            }
            unset($data['anexo']);

            $alvara = Alvara::create($data);

            return response()->json([
                'message' => 'Alvará criado com sucesso',
                'alvara' => $alvara
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar alvará',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $alvara = Alvara::findOrFail($id);
            return response()->json($alvara);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Alvará não encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        // Normaliza campos vazios antes da validação
        $input = $request->all();
        if (isset($input['unidade_id']) && $input['unidade_id'] === '') {
            $request->merge(['unidade_id' => null]);
        }

        $validator = Validator::make($request->all(), [
            'unidade_id' => 'nullable|exists:unidades,id',
            'tipo' => 'sometimes|required|string|max:255',
            'data_inicio' => 'sometimes|required|date',
            'data_vencimento' => 'sometimes|required|date',
            'valor_pago' => 'nullable|numeric|min:0',
            'anexo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $alvara = Alvara::findOrFail($id);
            $data = $request->all();

            unset($data['anexo']);

            if ($request->hasFile('anexo')) {
                if ($alvara->anexo_path && \Storage::disk('public')->exists($alvara->anexo_path)) {
                    \Storage::disk('public')->delete($alvara->anexo_path);
                }
                $file = $request->file('anexo');
                $nomeOriginal = $file->getClientOriginalName();
                $extensao = $file->getClientOriginalExtension();
                $nomeArquivo = time() . '_' . uniqid() . '.' . $extensao;
                $path = $file->storeAs('alvaras', $nomeArquivo, 'public');
                $data['anexo_path'] = $path;
                $data['anexo_nome'] = $nomeOriginal;
                $data['anexo_tipo'] = $extensao;
            }

            $alvara->update($data);

            return response()->json([
                'message' => 'Alvará atualizado com sucesso',
                'alvara' => $alvara
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Alvará não encontrado (pode ter sido excluído)',
                'error' => 'Alvará não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar alvará',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $alvara = Alvara::findOrFail($id);
            if ($alvara->anexo_path && \Storage::disk('public')->exists($alvara->anexo_path)) {
                \Storage::disk('public')->delete($alvara->anexo_path);
            }
            $alvara->delete();

            return response()->json(['message' => 'Alvará excluído com sucesso']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir alvará',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadAnexo($id)
    {
        try {
            $alvara = Alvara::findOrFail($id);
            if (!$alvara->anexo_path) {
                return response()->json(['message' => 'Este alvará não possui anexo'], 404);
            }
            $path = storage_path('app/public/' . $alvara->anexo_path);
            if (!file_exists($path)) {
                return response()->json(['message' => 'Arquivo não encontrado'], 404);
            }
            return response()->download($path, $alvara->anexo_nome ?: 'anexo');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao baixar anexo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removerAnexo($id)
    {
        try {
            $alvara = Alvara::findOrFail($id);
            if ($alvara->anexo_path && \Storage::disk('public')->exists($alvara->anexo_path)) {
                \Storage::disk('public')->delete($alvara->anexo_path);
            }
            $alvara->update([
                'anexo_path' => null,
                'anexo_nome' => null,
                'anexo_tipo' => null,
            ]);
            return response()->json(['message' => 'Anexo removido com sucesso']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover anexo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

