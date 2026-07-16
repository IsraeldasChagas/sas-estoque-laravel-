<?php

namespace App\Http\Controllers\Integrations;

use Illuminate\Http\Request;

/**
 * Webhooks — Fase 1: estrutura reservada para expansão futura.
 */
class WebhookController extends IntegrationBaseController
{
    public function index(Request $request)
    {
        $u = $this->authUsuario($request);
        if (! $this->podeVisualizar($u)) {
            return $this->json(['error' => 'Não autorizado.'], 403);
        }

        return $this->json([
            'fase' => 1,
            'implementado' => false,
            'mensagem' => 'Módulo de webhooks preparado para expansão futura.',
            'webhooks' => [],
        ]);
    }

    public function receive(Request $request, string $provider)
    {
        return $this->json([
            'fase' => 1,
            'implementado' => false,
            'mensagem' => 'Recebimento de webhooks será implementado em fase posterior.',
            'provider' => $provider,
        ], 501);
    }
}
