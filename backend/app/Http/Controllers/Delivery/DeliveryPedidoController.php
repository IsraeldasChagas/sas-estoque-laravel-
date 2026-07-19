<?php

namespace App\Http\Controllers\Delivery;

use App\Support\Delivery\DeliveryPedidoPresenter;
use App\Support\Delivery\DeliveryWhatsAppAvisoStatus;
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
        $configs = DB::table('dlv_loja_config')
            ->whereIn('unidade_id', $rows->pluck('unidade_id')->unique()->filter()->values())
            ->get()
            ->keyBy('unidade_id');

        return response()->json([
            'items' => $rows->map(function ($row) use ($configs) {
                $config = $configs->get($row->unidade_id);

                return [
                'id' => (int) $row->id,
                'unidade_id' => (int) $row->unidade_id,
                'codigo_publico' => (string) $row->codigo_publico,
                'status' => (string) $row->status,
                'canal' => (string) $row->canal,
                'fulfillment' => (string) $row->fulfillment,
                'cliente_nome' => (string) $row->cliente_nome,
                'pagamento_forma' => (string) ($row->pagamento_forma ?? ''),
                'pagamento_status' => (string) ($row->pagamento_status ?? ''),
                'pix_pendente' => DeliveryPedidoPresenter::pixPendenteConfirmacao($row),
                'pix_pago' => DeliveryPedidoPresenter::isPixPago($row),
                'pagamento_bloqueia_aceite' => DeliveryPedidoPresenter::bloqueiaAceitePorPix($row, $config),
                'subtotal' => (float) $row->subtotal,
                'frete_valor' => (float) $row->frete_valor,
                'total' => (float) $row->total,
                'created_at' => $row->created_at,
                ];
            })->values(),
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

        $novoStatus = (string) $validator->validated()['status'];
        $config = DB::table('dlv_loja_config')->where('unidade_id', $pedido->unidade_id)->first();
        if ($novoStatus === 'recebido' && ($pedido->status ?? '') === 'pendente_loja') {
            $this->pedidos->validarAceitePix($pedido, $config);
        }
        $atualizado = $this->pedidos->alterarStatus(
            $pedido,
            $novoStatus,
            (int) $usuario->id,
            $validator->validated()['detalhe'] ?? null
        );
        $payload = $this->pedidos->completo($atualizado);
        if ($config) {
            $waUrl = DeliveryWhatsAppAvisoStatus::url($atualizado, $config, $novoStatus);
            $payload['whatsapp_aviso_url'] = $waUrl;
            if ($waUrl === null && strtolower(trim((string) ($atualizado->canal ?? 'loja'))) === 'loja') {
                $payload['whatsapp_indisponivel'] = 'Não foi possível gerar o link do WhatsApp. Confira se o telefone do cliente tem DDD e número corretos.';
            }
        }

        return response()->json($payload);
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

    public function decisaoPendente(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        abort_unless($pedido, 404, 'Pedido não encontrado.');
        $this->access->autorizarRegistro($usuario, $pedido, 'Sem permissão para este pedido.');

        $data = $request->validate([
            'decisao' => 'required|string|in:aceitar,recusar',
        ]);

        if ($pedido->status !== 'pendente_loja') {
            return response()->json([
                'ok' => false,
                'message' => 'Este pedido não está aguardando confirmação.',
            ], 422);
        }

        $config = DB::table('dlv_loja_config')->where('unidade_id', $pedido->unidade_id)->first();
        if ($data['decisao'] === 'aceitar') {
            $this->pedidos->validarAceitePix($pedido, $config);
        }

        $novoStatus = $data['decisao'] === 'aceitar' ? 'recebido' : 'cancelado';
        $atualizado = $this->pedidos->alterarStatus($pedido, $novoStatus, (int) $usuario->id);
        $waUrl = $config
            ? DeliveryWhatsAppAvisoStatus::url($atualizado, $config, $novoStatus)
            : null;

        return response()->json([
            'ok' => true,
            'mensagem' => $novoStatus === 'recebido'
                ? 'Pedido aceito. Você pode seguir com o preparo.'
                : 'Pedido recusado. O estoque dos itens foi restaurado.',
            'proximo' => $this->pedidos->proximoPedidoPendentePoll((int) $atualizado->unidade_id),
            'whatsapp_aviso_url' => $waUrl,
        ]);
    }

    public function confirmarPagamento(Request $request, int $id): JsonResponse
    {
        $usuario = $this->auth($request, 'deliveryPedidos');
        $pedido = DB::table('dlv_pedidos')->where('id', $id)->first();
        abort_unless($pedido, 404, 'Pedido não encontrado.');
        $this->access->autorizarRegistro($usuario, $pedido, 'Sem permissão para este pedido.');

        $atualizado = $this->pedidos->confirmarPagamentoPix($pedido, (int) $usuario->id);

        return response()->json(array_merge(
            $this->pedidos->completo($atualizado),
            [
                'ok' => true,
                'mensagem' => 'Pagamento PIX confirmado.',
            ]
        ));
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
