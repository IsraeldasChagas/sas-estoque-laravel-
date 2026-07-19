<?php

namespace App\Services\Payments;

use App\Services\Delivery\DeliveryPedidoService;
use App\Support\Delivery\DeliveryGatewayConfig;
use App\Support\Delivery\DeliveryLojaCheckoutHelper;
use App\Support\Delivery\DeliveryPedidoPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class DeliveryCardGatewayService
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private DeliveryPedidoService $pedidos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function iniciarCheckout(object $pedido, object $config, array $urls): array
    {
        if (! DeliveryPedidoPresenter::isCartaoOnline($pedido)) {
            return ['modo' => 'nao_cartao_online'];
        }

        if (! DeliveryGatewayConfig::pagamentoOnlineAtivo($config)) {
            throw ValidationException::withMessages([
                'pagamento' => 'Pagamento online não está configurado nesta loja.',
            ]);
        }

        $provider = $this->gateways->resolverCartao(DeliveryGatewayConfig::provedor($config));
        if (! $provider) {
            throw ValidationException::withMessages([
                'pagamento' => 'Provedor de pagamento online inválido.',
            ]);
        }

        $result = $provider->criarCheckout(
            $pedido,
            $config,
            DeliveryGatewayConfig::credenciais($config),
            $urls
        );

        if (! ($result['ok'] ?? false)) {
            throw ValidationException::withMessages([
                'pagamento' => (string) ($result['mensagem'] ?? 'Não foi possível abrir pagamento online.'),
            ]);
        }

        $this->persistirCheckout($pedido, $provider->codigo(), $result);

        return [
            'modo' => 'cartao_online',
            'automatico' => true,
            'provedor' => $provider->codigo(),
            'externo_id' => $result['externo_id'] ?? null,
            'checkout_url' => $result['checkout_url'] ?? null,
            'gateway_status' => $result['status'] ?? 'pending',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPublico(object $pedido, object $config): array
    {
        $base = [
            'pagamento_forma' => $pedido->pagamento_forma,
            'pagamento_status' => $pedido->pagamento_status,
            'cartao_online_pago' => DeliveryPedidoPresenter::isCartaoOnlinePago($pedido),
            'cartao_online_pendente' => DeliveryPedidoPresenter::cartaoOnlinePendente($pedido),
            'gateway_status' => $pedido->pagamento_gateway_status ?? null,
            'checkout_url' => $pedido->pagamento_checkout_url ?? null,
        ];

        if (! DeliveryPedidoPresenter::isCartaoOnline($pedido) || DeliveryPedidoPresenter::isCartaoOnlinePago($pedido)) {
            return $base;
        }

        if (DeliveryGatewayConfig::gatewayConfigurado($config)) {
            $provider = $this->gateways->resolverCartao(
                (string) ($pedido->pagamento_externo_provedor ?? DeliveryGatewayConfig::provedor($config))
            );
            if ($provider) {
                $consulta = $provider->consultarPagamento($pedido, DeliveryGatewayConfig::credenciais($config));
                if (($consulta['pago'] ?? false) === true) {
                    $pedido = $this->pedidos->confirmarPagamentoGateway($pedido, null, 'webhook');

                    return array_merge($base, [
                        'pagamento_status' => $pedido->pagamento_status,
                        'cartao_online_pago' => true,
                        'cartao_online_pendente' => false,
                        'gateway_status' => 'approved',
                        'confirmado_agora' => true,
                    ]);
                }
                if (! empty($consulta['status'])) {
                    DB::table('dlv_pedidos')->where('id', $pedido->id)->update([
                        'pagamento_gateway_status' => $consulta['status'],
                        'updated_at' => now(),
                    ]);
                    $base['gateway_status'] = $consulta['status'];
                }
            }
        }

        return $base;
    }

    /** @param  array<string, mixed>  $result */
    private function persistirCheckout(object $pedido, string $provedor, array $result): void
    {
        $update = [
            'pagamento_externo_id' => $result['externo_id'] ?? null,
            'pagamento_externo_provedor' => $provedor,
            'pagamento_gateway_status' => $result['status'] ?? 'pending',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('dlv_pedidos', 'pagamento_checkout_url')) {
            $update['pagamento_checkout_url'] = $result['checkout_url'] ?? null;
        }
        DB::table('dlv_pedidos')->where('id', $pedido->id)->update($update);
    }
}
