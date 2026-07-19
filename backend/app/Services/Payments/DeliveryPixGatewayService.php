<?php

namespace App\Services\Payments;

use App\Services\Delivery\DeliveryPedidoService;
use App\Support\Delivery\DeliveryGatewayConfig;
use App\Support\Delivery\DeliveryLojaCheckoutHelper;
use App\Support\Delivery\DeliveryPedidoPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DeliveryPixGatewayService
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private DeliveryPedidoService $pedidos,
    ) {}

    /**
     * Após criar pedido PIX: gera cobrança automática ou mantém manual.
     *
     * @return array<string, mixed>
     */
    public function iniciarPix(object $pedido, object $config): array
    {
        if (! DeliveryPedidoPresenter::isPix($pedido)) {
            return ['modo' => 'nao_pix'];
        }

        $modo = DeliveryGatewayConfig::pixModo($config);
        $manual = $this->snapshotManual($config, $pedido);

        if ($modo === DeliveryGatewayConfig::PIX_MODO_MANUAL || ! DeliveryGatewayConfig::gatewayConfigurado($config)) {
            return array_merge($manual, [
                'modo' => DeliveryGatewayConfig::PIX_MODO_MANUAL,
                'automatico' => false,
            ]);
        }

        $provider = $this->gateways->resolver(DeliveryGatewayConfig::provedor($config));
        if (! $provider) {
            return $this->fallbackManual($config, $pedido, $modo, 'Provedor de pagamento inválido.');
        }

        $result = $provider->criarCobranca($pedido, $config, DeliveryGatewayConfig::credenciais($config));
        if (! ($result['ok'] ?? false)) {
            if ($modo === DeliveryGatewayConfig::PIX_MODO_HIBRIDO) {
                return $this->fallbackManual($config, $pedido, $modo, (string) ($result['mensagem'] ?? 'Falha no gateway.'));
            }

            return [
                'modo' => DeliveryGatewayConfig::PIX_MODO_AUTOMATICO,
                'automatico' => false,
                'erro' => (string) ($result['mensagem'] ?? 'Não foi possível gerar PIX automático.'),
            ];
        }

        $this->persistirCobranca($pedido, $provider->codigo(), $result);

        return [
            'modo' => $modo,
            'automatico' => true,
            'provedor' => $provider->codigo(),
            'externo_id' => $result['externo_id'] ?? null,
            'payload' => $result['payload'] ?? null,
            'expira_em' => isset($result['expira_em']) ? (string) $result['expira_em'] : null,
            'gateway_status' => $result['status'] ?? 'pending',
            'qr_data_uri' => ! empty($result['payload'])
                ? DeliveryLojaCheckoutHelper::pixQrCodeDataUri((object) ['pix_copia_cola' => $result['payload']])
                : null,
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
            'pix_pago' => DeliveryPedidoPresenter::isPixPago($pedido),
            'pix_pendente' => DeliveryPedidoPresenter::pixPendenteConfirmacao($pedido),
            'gateway_status' => $pedido->pagamento_gateway_status ?? null,
            'automatico' => trim((string) ($pedido->pagamento_externo_id ?? '')) !== '',
            'expira_em' => $pedido->pagamento_pix_expira_em ?? null,
        ];

        if (! DeliveryPedidoPresenter::isPix($pedido) || DeliveryPedidoPresenter::isPixPago($pedido)) {
            return $base;
        }

        if (DeliveryGatewayConfig::gatewayConfigurado($config)
            && trim((string) ($pedido->pagamento_externo_id ?? '')) !== '') {
            $provider = $this->gateways->resolver((string) ($pedido->pagamento_externo_provedor ?? DeliveryGatewayConfig::provedor($config)));
            if ($provider) {
                $consulta = $provider->consultarCobranca($pedido, DeliveryGatewayConfig::credenciais($config));
                if (($consulta['pago'] ?? false) === true) {
                    $pedido = $this->pedidos->confirmarPagamentoPix($pedido, null, 'webhook');

                    return array_merge($base, [
                        'pagamento_status' => $pedido->pagamento_status,
                        'pix_pago' => true,
                        'pix_pendente' => false,
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

        $payload = trim((string) ($pedido->pagamento_pix_payload ?? ''));
        if ($payload !== '') {
            $base['payload'] = $payload;
            $base['qr_data_uri'] = DeliveryLojaCheckoutHelper::pixQrCodeDataUri((object) ['pix_copia_cola' => $payload]);
        }

        return $base;
    }

    /**
     * @return array{ok:bool,mensagem?:string,pedido_id?:int}
     */
    public function processarWebhook(string $providerCode, array $payload, ?string $signature): array
    {
        $provider = $this->gateways->resolver($providerCode);
        if (! $provider) {
            return ['ok' => false, 'mensagem' => 'Provedor não suportado.'];
        }

        $externoHint = (string) (data_get($payload, 'data.id') ?? $payload['id'] ?? '');
        $pedido = null;
        if ($externoHint !== '') {
            $pedido = DB::table('dlv_pedidos')
                ->where('pagamento_forma', DeliveryPedidoPresenter::PAGAMENTO_PIX)
                ->where('pagamento_externo_id', $externoHint)
                ->first();
        }

        $config = $pedido
            ? DB::table('dlv_loja_config')->where('unidade_id', $pedido->unidade_id)->first()
            : null;
        $credenciais = $config ? DeliveryGatewayConfig::credenciais($config) : [];

        $interpretado = $provider->interpretarWebhook($payload, $credenciais, $signature);
        if (! ($interpretado['ok'] ?? false)) {
            return ['ok' => false, 'mensagem' => $interpretado['mensagem'] ?? 'Webhook ignorado.'];
        }

        if (! ($interpretado['pago'] ?? false)) {
            return ['ok' => true, 'mensagem' => 'Pagamento ainda não aprovado.', 'status' => $interpretado['status'] ?? null];
        }

        $externoId = (string) ($interpretado['externo_id'] ?? $externoHint);
        $referencia = trim((string) ($interpretado['referencia'] ?? ''));

        if (! $pedido) {
            $query = DB::table('dlv_pedidos')->where('pagamento_forma', DeliveryPedidoPresenter::PAGAMENTO_PIX);
            if ($externoId !== '') {
                $query->where('pagamento_externo_id', $externoId);
            } elseif ($referencia !== '') {
                $query->where('codigo_publico', $referencia);
            } else {
                return ['ok' => false, 'mensagem' => 'Pedido não identificado no webhook.'];
            }
            $pedido = $query->first();
        }

        if (! $pedido) {
            return ['ok' => false, 'mensagem' => 'Pedido não encontrado para este pagamento.'];
        }

        if (DeliveryPedidoPresenter::isPixPago($pedido)) {
            return ['ok' => true, 'mensagem' => 'Pagamento já confirmado.', 'pedido_id' => (int) $pedido->id];
        }

        $this->pedidos->confirmarPagamentoPix($pedido, null, 'webhook');

        return ['ok' => true, 'mensagem' => 'Pagamento PIX confirmado automaticamente.', 'pedido_id' => (int) $pedido->id];
    }

    /** @param  array<string, mixed>  $result */
    private function persistirCobranca(object $pedido, string $provedor, array $result): void
    {
        $update = [
            'pagamento_externo_id' => $result['externo_id'] ?? null,
            'pagamento_externo_provedor' => $provedor,
            'pagamento_pix_payload' => $result['payload'] ?? null,
            'pagamento_gateway_status' => $result['status'] ?? 'pending',
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('dlv_pedidos', 'pagamento_pix_expira_em') && ! empty($result['expira_em'])) {
            $update['pagamento_pix_expira_em'] = $result['expira_em'];
        }
        DB::table('dlv_pedidos')->where('id', $pedido->id)->update($update);
    }

    /** @return array<string, mixed> */
    private function snapshotManual(object $config, object $pedido): array
    {
        return [
            'payload' => trim((string) ($config->pix_copia_cola ?? '')) ?: null,
            'qr_data_uri' => DeliveryLojaCheckoutHelper::pixQrCodeDataUri($config),
            'chave' => trim((string) ($config->pix_chave ?? '')) ?: null,
            'total' => (float) $pedido->total,
        ];
    }

    /** @return array<string, mixed> */
    private function fallbackManual(object $config, object $pedido, string $modo, string $motivo): array
    {
        return array_merge($this->snapshotManual($config, $pedido), [
            'modo' => $modo,
            'automatico' => false,
            'fallback_manual' => true,
            'aviso' => $motivo,
        ]);
    }
}
