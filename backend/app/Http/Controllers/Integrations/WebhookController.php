<?php

namespace App\Http\Controllers\Integrations;

use App\Services\Payments\DeliveryPixGatewayService;
use Illuminate\Http\Request;

class WebhookController extends IntegrationBaseController
{
    public function __construct(
        private readonly DeliveryPixGatewayService $pixGateway,
    ) {}

    public function index(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        return $this->json([
            'implementado' => true,
            'mensagem' => 'Webhooks de pagamento delivery (PIX automático).',
            'provedores' => [
                'mercado_pago' => url('/api/integracoes/webhooks/mercado_pago'),
                'asaas' => url('/api/integracoes/webhooks/asaas'),
            ],
        ]);
    }

    public function receive(Request $request, string $provider)
    {
        $signature = $request->header('X-Signature')
            ?? $request->header('X-Hub-Signature')
            ?? $request->header('X-Request-Id');

        $result = $this->pixGateway->processarWebhook(
            $provider,
            $request->all(),
            is_string($signature) ? $signature : null
        );

        $status = ($result['ok'] ?? false) ? 200 : 422;

        return $this->json($result, $status);
    }
}
