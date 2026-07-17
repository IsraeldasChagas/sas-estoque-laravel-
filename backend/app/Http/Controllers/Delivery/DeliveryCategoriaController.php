<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryCategoriaController extends DeliveryBaseController
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $query = DB::table('dlv_categorias');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        if ($busca = trim((string) $request->query('busca', ''))) {
            $query->where('nome', 'like', '%'.$busca.'%');
        }

        $items = $query->orderBy('ordem')->orderBy('nome')->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $agora = now();

        $id = DB::table('dlv_categorias')->insertGetId([
            'unidade_id' => $unidadeId,
            'nome' => $data['nome'],
            'ordem' => (int) ($data['ordem'] ?? 0),
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return response()->json(DB::table('dlv_categorias')->where('id', $id)->first(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $row = DB::table('dlv_categorias')->where('id', $id)->first();
        abort_unless($row, 404, 'Categoria não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($row);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $row = DB::table('dlv_categorias')->where('id', $id)->first();
        abort_unless($row, 404, 'Categoria não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);

        DB::table('dlv_categorias')->where('id', $id)->update([
            'nome' => $data['nome'] ?? $row->nome,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('dlv_categorias')->where('id', $id)->first());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryCategorias');
        $row = DB::table('dlv_categorias')->where('id', $id)->first();
        abort_unless($row, 404, 'Categoria não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);
        DB::table('dlv_produtos')->where('categoria_id', $id)->update(['categoria_id' => null, 'updated_at' => now()]);
        DB::table('dlv_categorias')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:120',
            'ordem' => 'nullable|integer|min:0',
            'ativo' => 'nullable|boolean',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
