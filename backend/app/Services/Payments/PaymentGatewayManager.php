<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentCardProviderInterface;
use App\Contracts\Payments\PaymentPixProviderInterface;
use App\Services\Payments\Providers\AsaasPixProvider;
use App\Services\Payments\Providers\MercadoPagoCardProvider;
use App\Services\Payments\Providers\MercadoPagoPixProvider;
use App\Support\Delivery\DeliveryGatewayConfig;
use InvalidArgumentException;

final class PaymentGatewayManager
{
    /** @var array<string, PaymentPixProviderInterface> */
    private array $providers;

    /** @var array<string, PaymentCardProviderInterface> */
    private array $cardProviders;

    public function __construct()
    {
        $this->providers = [
            DeliveryGatewayConfig::PROVEDOR_MERCADO_PAGO => new MercadoPagoPixProvider,
            DeliveryGatewayConfig::PROVEDOR_ASAAS => new AsaasPixProvider,
        ];
        $this->cardProviders = [
            DeliveryGatewayConfig::PROVEDOR_MERCADO_PAGO => new MercadoPagoCardProvider,
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

    public function resolverCartao(?string $codigo): ?PaymentCardProviderInterface
    {
        $codigo = strtolower(trim((string) $codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->cardProviders[$codigo] ?? null;
    }

    /** @return array<string, string> */
    public function listarCartao(): array
    {
        $out = [];
        foreach ($this->cardProviders as $code => $provider) {
            $out[$code] = $provider->rotulo();
        }

        return $out;
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
