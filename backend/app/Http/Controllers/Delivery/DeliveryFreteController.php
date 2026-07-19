<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryAccessService;
use App\Services\Delivery\DeliveryFreteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryFreteController extends DeliveryBaseController
{
    public function __construct(
        DeliveryAccessService $access,
        private readonly DeliveryFreteService $frete,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryFretes');
        $query = DB::table('dlv_frete_faixas_cep');
        $this->access->aplicarEscopo($query, $usuario, $request);

        return response()->json([
            'items' => $query->orderBy('ordem')->orderBy('cep_inicio')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryFretes');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $agora = now();

        $id = DB::table('dlv_frete_faixas_cep')->insertGetId([
            'unidade_id' => $unidadeId,
            'cep_inicio' => $this->cep($data['cep_inicio']),
            'cep_fim' => $this->cep($data['cep_fim']),
            'taxa' => round((float) $data['taxa'], 2),
            'label' => $data['label'] ?? null,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'ordem' => (int) ($data['ordem'] ?? 0),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return response()->json(DB::table('dlv_frete_faixas_cep')->where('id', $id)->first(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryFretes');
        $row = DB::table('dlv_frete_faixas_cep')->where('id', $id)->first();
        abort_unless($row, 404, 'Faixa não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);

        return response()->json($row);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryFretes');
        $row = DB::table('dlv_frete_faixas_cep')->where('id', $id)->first();
        abort_unless($row, 404, 'Faixa não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);
        $data = $this->validar($request, false);

        DB::table('dlv_frete_faixas_cep')->where('id', $id)->update([
            'cep_inicio' => array_key_exists('cep_inicio', $data) ? $this->cep($data['cep_inicio']) : $row->cep_inicio,
            'cep_fim' => array_key_exists('cep_fim', $data) ? $this->cep($data['cep_fim']) : $row->cep_fim,
            'taxa' => array_key_exists('taxa', $data) ? round((float) $data['taxa'], 2) : $row->taxa,
            'label' => array_key_exists('label', $data) ? $data['label'] : $row->label,
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : (bool) $row->ativo,
            'ordem' => array_key_exists('ordem', $data) ? (int) $data['ordem'] : $row->ordem,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('dlv_frete_faixas_cep')->where('id', $id)->first());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryFretes');
        $row = DB::table('dlv_frete_faixas_cep')->where('id', $id)->first();
        abort_unless($row, 404, 'Faixa não encontrada.');
        $this->access->autorizarRegistro($usuario, $row);
        DB::table('dlv_frete_faixas_cep')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    public function calcular(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryFretes');
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $request->all());

        $validator = Validator::make($request->all(), [
            'cep' => 'nullable|string|max:16',
            'subtotal' => 'nullable|numeric|min:0',
            'fulfillment' => 'nullable|string',
            'chuva' => 'nullable|boolean',
            'unidade_id' => 'nullable|integer',
            'endereco' => 'nullable|string|max:500',
            'logradouro' => 'nullable|string|max:180',
            'rua' => 'nullable|string|max:180',
            'numero' => 'nullable|string|max:40',
            'bairro' => 'nullable|string|max:120',
            'cidade' => 'nullable|string|max:120',
            'uf' => 'nullable|string|size:2',
            'complemento' => 'nullable|string|max:255',
            'cliente_telefone' => 'nullable|string|max:32',
            'telefone' => 'nullable|string|max:32',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $resultado = $this->frete->calcular($unidadeId, $validator->validated());
        $mensagem = (string) ($resultado['mensagem'] ?? '');
        $label = $resultado['bloqueado'] ?? false
            ? 'Entrega indisponível'
            : (($resultado['frete_gratis'] ?? false) ? 'Frete grátis' : ($resultado['rotulo'] ?? $mensagem ?: 'Frete calculado'));

        return response()->json(array_merge($resultado, [
            'label' => $label,
            'message' => $mensagem,
            'mensagem' => $mensagem,
        ]));
    }

    private function cep(string $value): string
    {
        $cep = preg_replace('/\D+/', '', $value) ?? '';
        abort_unless(strlen($cep) === 8, 422, 'CEP deve ter 8 dígitos.');

        return $cep;
    }

    private function validar(Request $request, bool $criar = true): array
    {
        $rules = [
            'cep_inicio' => ($criar ? 'required' : 'sometimes').'|string',
            'cep_fim' => ($criar ? 'required' : 'sometimes').'|string',
            'taxa' => ($criar ? 'required' : 'sometimes').'|numeric|min:0',
            'label' => 'nullable|string|max:120',
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
