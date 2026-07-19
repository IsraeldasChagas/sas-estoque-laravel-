<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentPixProviderInterface;
use App\Services\Payments\Providers\AsaasPixProvider;
use App\Services\Payments\Providers\MercadoPagoPixProvider;
use App\Support\Delivery\DeliveryGatewayConfig;
use InvalidArgumentException;

final class PaymentGatewayManager
{
    /** @var array<string, PaymentPixProviderInterface> */
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            DeliveryGatewayConfig::PROVEDOR_MERCADO_PAGO => new MercadoPagoPixProvider,
            DeliveryGatewayConfig::PROVEDOR_ASAAS => new AsaasPixProvider,
        ];
    }

    public function resolver(?string $codigo): ?PaymentPixProviderInterface
    {
        $codigo = strtolower(trim((string) $codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->providers[$codigo] ?? null;
    }

    public function resolverOuFalhar(?string $codigo): PaymentPixProviderInterface
    {
        $provider = $this->resolver($codigo);
        if (! $provider) {
            throw new InvalidArgumentException('Provedor de pagamento não suportado: '.$codigo);
        }

        return $provider;
    }

    /** @return array<string, string> */
    public function listar(): array
    {
        $out = [];
        foreach ($this->providers as $code => $provider) {
            $out[$code] = $provider->rotulo();
        }

        return $out;
    }
}
