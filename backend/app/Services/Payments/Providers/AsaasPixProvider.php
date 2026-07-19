<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentPixProviderInterface;

/** Provedor reservado — configure credenciais e implemente quando ativar Asaas. */
final class AsaasPixProvider implements PaymentPixProviderInterface
{
    public function codigo(): string
    {
        return 'asaas';
    }

    public function rotulo(): string
    {
        return 'Asaas';
    }

    public function criarCobranca(object $pedido, object $config, array $credenciais): array
    {
        return ['ok' => false, 'mensagem' => 'Integração Asaas PIX em preparação. Use Mercado Pago ou modo manual por enquanto.'];
    }

    public function consultarCobranca(object $pedido, array $credenciais): array
    {
        return ['ok' => false, 'mensagem' => 'Asaas ainda não disponível.'];
    }

    public function interpretarWebhook(array $payload, array $credenciais, ?string $signature): array
    {
        return ['ok' => false, 'mensagem' => 'Webhook Asaas ainda não disponível.'];
    }
}
