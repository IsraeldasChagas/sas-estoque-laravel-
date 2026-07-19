<?php

namespace App\Http\Controllers\Delivery;

use App\Services\Delivery\DeliveryAccessService;
use App\Services\Delivery\DeliveryPedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DeliveryPedidoController extends DeliveryBaseController
{
    public function __construct(
        DeliveryAccessService $access,
        private readonly DeliveryPedidoService $pedidos,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $query = DB::table('dlv_pedidos');
        $this->access->aplicarEscopo($query, $usuario, $request);

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }
        if ($busca = trim((string) $request->query('busca', ''))) {
            $like = '%'.$busca.'%';
            $query->where(function ($q) use ($like) {
                $q->where('codigo_publico', 'like', $like)
                    ->orWhere('cliente_nome', 'like', $like)
                    ->orWhere('cliente_telefone', 'like', $like);
            });
        }

        $limit = max(1, min(200, (int) $request->query('limit', 100)));
        $rows = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'items' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'unidade_id' => (int) $row->unidade_id,
                'codigo_publico' => (string) $row->codigo_publico,
                'status' => (string) $row->status,
                'canal' => (string) $row->canal,
                'fulfillment' => (string) $row->fulfillment,
                'cliente_nome' => (string) $row->cliente_nome,
                'subtotal' => (float) $row->subtotal,
                'frete_valor' => (float) $row->frete_valor,
                'total' => (float) $row->total,
                'created_at' => $row->created_at,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $data = $this->validar($request);
        $unidadeId = $this->access->exigirUnidade($request, $usuario, $data);
        $id = $this->pedidos->criar($unidadeId, $data, (int) $usuario->id);
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();

        return response()->json($this->pedidos->completo($pedido), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        abort_unless($pedido, 404, 'Pedido não encontrado.');
        $this->access->autorizarRegistro($usuario, $pedido, 'Sem permissão para este pedido.');

        return response()->json($this->pedidos->completo($pedido));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        abort_unless($pedido, 404, 'Pedido não encontrado.');
        $this->access->autorizarRegistro($usuario, $pedido, 'Sem permissão para este pedido.');

        $validator = Validator::make($request->all(), [
            'cliente_nome' => 'sometimes|string|max:160',
            'cliente_telefone' => 'nullable|string|max:30',
            'cliente_whatsapp' => 'nullable|string|max:30',
            'observacoes' => 'nullable|string',
            'entregador_id' => 'nullable|integer',
            'pagamento_forma' => 'nullable|string|max:40',
            'pagamento_status' => 'nullable|string|max:40',
            'pagamento_troco_para' => 'nullable|numeric|min:0',
            'endereco_texto' => 'nullable|string',
            'endereco_cep' => 'nullable|string',
            'endereco_rua' => 'nullable|string|max:180',
            'endereco_numero' => 'nullable|string|max:40',
            'endereco_bairro' => 'nullable|string|max:120',
            'endereco_cidade' => 'nullable|string|max:120',
            'endereco_uf' => 'nullable|string|max:2',
            'endereco_complemento' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $data = $validator->validated();

        $update = [];
        foreach ($data as $key => $value) {
            if ($key === 'endereco_cep' && $value !== null) {
                $update[$key] = preg_replace('/\D+/', '', (string) $value);
            } else {
                $update[$key] = $value;
            }
        }
        $update['updated_at'] = now();
        DB::table('dlv_pedidos')->where('id', $id)->update($update);

        return response()->json($this->pedidos->completo(DB::table('dlv_pedidos')->where('id', $id)->first()));
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        abort_unless($pedido, 404, 'Pedido não encontrado.');
        $this->access->autorizarRegistro($usuario, $pedido, 'Sem permissão para este pedido.');

        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
            'detalhe' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $atualizado = $this->pedidos->alterarStatus(
            $pedido,
            (string) $validator->validated()['status'],
            (int) $usuario->id,
            $validator->validated()['detalhe'] ?? null
        );

        return response()->json($this->pedidos->completo($atualizado));
    }

    public function pollPendentes(Request $request): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $unidadeId = $this->access->unidadeId($request, $usuario);
        if (! $unidadeId) {
            return response()->json(['enabled' => false, 'pedidos' => []]);
        }

        $config = DB::table('dlv_loja_config')->where('unidade_id', $unidadeId)->first();
        if (! $config || ! (bool) ($config->confirmar_pedidos ?? true)) {
            return response()->json(['enabled' => false, 'pedidos' => []]);
        }

        return response()->json([
            'enabled' => true,
            'pedidos' => $this->pedidos->serializarPedidosPendentesPoll($unidadeId),
        ]);
    }

    public function imprimir(Request $request, int $id): View
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        abort_unless($pedido, 404, 'Pedido não encontrado.');
        $this->access->autorizarRegistro($usuario, $pedido, 'Sem permissão para este pedido.');

        $config = DB::table('dlv_loja_config')->where('unidade_id', $pedido->unidade_id)->first();
        abort_unless($config, 404, 'Configuração da loja não encontrada.');

        $itens = DB::table('dlv_pedido_itens')
            ->where('pedido_id', $pedido->id)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        return view('delivery.admin.pedidos.imprimir', compact('config', 'pedido', 'itens'));
    }

    private function validar(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'unidade_id' => 'nullable|integer',
            'canal' => 'nullable|string|max:40',
            'fulfillment' => 'nullable|in:entrega,retirada,pickup',
            'cliente_nome' => 'required|string|max:160',
            'cliente_telefone' => 'nullable|string|max:30',
            'cliente_whatsapp' => 'nullable|string|max:30',
            'endereco_texto' => 'nullable|string',
            'endereco_cep' => 'nullable|string',
            'endereco_rua' => 'nullable|string|max:180',
            'endereco_numero' => 'nullable|string|max:40',
            'endereco_bairro' => 'nullable|string|max:120',
            'endereco_cidade' => 'nullable|string|max:120',
            'endereco_uf' => 'nullable|string|max:2',
            'endereco_complemento' => 'nullable|string',
            'pagamento_forma' => 'nullable|string|max:40',
            'pagamento_status' => 'nullable|string|max:40',
            'pagamento_troco_para' => 'nullable|numeric|min:0',
            'entregador_id' => 'nullable|integer',
            'observacoes' => 'nullable|string',
            'chuva' => 'nullable|boolean',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'nullable|numeric|min:0.001',
            'itens.*.opcoes' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
