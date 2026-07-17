<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryAdicionalController extends DeliveryBaseController
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $query = DB::table('dlv_adicionais');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($tipo = trim((string) $request->query('tipo', ''))) {
            $query->where('tipo', $tipo);
        }
        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }
        if ($busca = trim((string) $request->query('busca', ''))) {
            $query->where('nome', 'like', '%'.$busca.'%');
        }

        return response()->json(['items' => $query->orderBy('ordem')->orderBy('nome')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $agora = now();

        $id = DB::table('dlv_adicionais')->insertGetId([
            'unidade_id' => $unidadeId,
            'nome' => $data['nome'],
            'tipo' => $data['tipo'] ?? 'acrescentar',
            'preco' => round((float) ($data['preco'] ?? 0), 2),
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'ordem' => (int) ($data['ordem'] ?? 0),
            'foto_path' => $data['foto_path'] ?? null,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return response()->json(DB::table('dlv_adicionais')->where('id', $id)->first(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $row = DB::table('dlv_adicionais')->where('id', $id)->first();
        abort_unless($row, 404, 'Adicional não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($row);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $row = DB::table('dlv_adicionais')->where('id', $id)->first();
        abort_unless($row, 404, 'Adicional não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);

        DB::table('dlv_adicionais')->where('id', $id)->update([
            'nome' => $data['nome'] ?? $row->nome,
            'tipo' => $data['tipo'] ?? $row->tipo,
            'preco' => array_key_exists('preco', $data) ? round((float) $data['preco'], 2) : $row->preco,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
            'foto_path' => array_key_exists('foto_path', $data) ? $data['foto_path'] : $row->foto_path,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('dlv_adicionais')->where('id', $id)->first());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryAdicionais');
        $row = DB::table('dlv_adicionais')->where('id', $id)->first();
        abort_unless($row, 404, 'Adicional não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        DB::transaction(function () use ($id) {
            DB::table('dlv_produto_adicional')->where('adicional_id', $id)->delete();
            DB::table('dlv_adicionais')->where('id', $id)->delete();
        });

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:160',
            'tipo' => 'nullable|in:acrescentar,retirar',
            'preco' => 'nullable|numeric|min:0',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0',
            'foto_path' => 'nullable|string|max:255',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
