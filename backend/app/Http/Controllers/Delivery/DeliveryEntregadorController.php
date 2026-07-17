<?php

namespace App\Http\Controllers\Delivery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryEntregadorController extends DeliveryBaseController
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $query = DB::table('dlv_entregadores');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->query('ativo'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        return response()->json(['items' => $query->orderBy('ordem')->orderBy('nome')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $agora = now();

        $id = DB::table('dlv_entregadores')->insertGetId([
            'unidade_id' => $unidadeId,
            'nome' => $data['nome'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'moto_placa' => $data['moto_placa'] ?? null,
            'moto_modelo' => $data['moto_modelo'] ?? null,
            'foto_path' => $data['foto_path'] ?? null,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'ordem' => (int) ($data['ordem'] ?? 0),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return response()->json(DB::table('dlv_entregadores')->where('id', $id)->first(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($row);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);

        DB::table('dlv_entregadores')->where('id', $id)->update([
            'nome' => $data['nome'] ?? $row->nome,
            'whatsapp' => array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $row->whatsapp,
            'telefone' => array_key_exists('telefone', $data) ? $data['telefone'] : $row->telefone,
            'moto_placa' => array_key_exists('moto_placa', $data) ? $data['moto_placa'] : $row->moto_placa,
            'moto_modelo' => array_key_exists('moto_modelo', $data) ? $data['moto_modelo'] : $row->moto_modelo,
            'foto_path' => array_key_exists('foto_path', $data) ? $data['foto_path'] : $row->foto_path,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('dlv_entregadores')->where('id', $id)->first());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryEntregadores');
        $row = DB::table('dlv_entregadores')->where('id', $id)->first();
        abort_unless($row, 404, 'Entregador não encontrado.');
        $this->access->autorizarRegistro($usuario, $row);
        DB::table('dlv_entregadores')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'nome' => ($criar ? 'required' : 'sometimes').'|string|max:160',
            'whatsapp' => 'nullable|string|max:30',
            'telefone' => 'nullable|string|max:30',
            'moto_placa' => 'nullable|string|max:20',
            'moto_modelo' => 'nullable|string|max:80',
            'foto_path' => 'nullable|string|max:255',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0',
            'unidade_id' => 'nullable|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
