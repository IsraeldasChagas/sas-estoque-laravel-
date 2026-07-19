<?php

namespace App\Support\Delivery;

final class DeliveryGatewayConfig
{
    public const PIX_MODO_MANUAL = 'manual';

    public const PIX_MODO_AUTOMATICO = 'automatico';

    public const PIX_MODO_HIBRIDO = 'hibrido';

    public const PROVEDOR_MERCADO_PAGO = 'mercado_pago';

    public const PROVEDOR_ASAAS = 'asaas';

    public const PROVEDOR_PAGBANK = 'pagbank';

    public const PAGAMENTO_CARTAO_ONLINE = 'cartao_online';

    /** @return array<string, string> */
    public static function provedoresRotulos(): array
    {
        return [
            self::PROVEDOR_MERCADO_PAGO => 'Mercado Pago',
            self::PROVEDOR_ASAAS => 'Asaas',
            self::PROVEDOR_PAGBANK => 'PagBank / PagSeguro',
        ];
    }

    /** @return array<string, string> */
    public static function pixModosRotulos(): array
    {
        return [
            self::PIX_MODO_MANUAL => 'Manual (chave/QR da loja)',
            self::PIX_MODO_AUTOMATICO => 'Automático (gateway confirma sozinho)',
            self::PIX_MODO_HIBRIDO => 'Híbrido (gateway + fallback manual)',
        ];
    }

    public static function pixModo(object $config): string
    {
        $modo = strtolower(trim((string) ($config->pix_modo ?? self::PIX_MODO_MANUAL)));

        return in_array($modo, [self::PIX_MODO_MANUAL, self::PIX_MODO_AUTOMATICO, self::PIX_MODO_HIBRIDO], true)
            ? $modo
            : self::PIX_MODO_MANUAL;
    }

    public static function provedor(object $config): ?string
    {
        $p = strtolower(trim((string) ($config->pagamento_gateway ?? '')));

        return $p !== '' ? $p : null;
    }

    public static function gatewayConfigurado(object $config): bool
    {
        return self::provedor($config) !== null
            && trim((string) ($config->pagamento_gateway_token ?? '')) !== '';
    }

    public static function pagamentoOnlineAtivo(object $config): bool
    {
        return (bool) ($config->pagamento_online_ativo ?? false) && self::gatewayConfigurado($config);
    }

    public static function usaPixAutomatico(object $config): bool
    {
        $modo = self::pixModo($config);

        return in_array($modo, [self::PIX_MODO_AUTOMATICO, self::PIX_MODO_HIBRIDO], true)
            && self::gatewayConfigurado($config);
    }

    public static function pixExpiracaoMinutos(object $config): int
    {
        $min = (int) ($config->pix_expiracao_minutos ?? 30);

        return max(5, min(1440, $min));
    }

    /** @return array<string, mixed> */
    public static function credenciais(object $config): array
    {
        return [
            'provedor' => self::provedor($config),
            'token' => trim((string) ($config->pagamento_gateway_token ?? '')),
            'public_key' => trim((string) ($config->pagamento_gateway_public_key ?? '')),
            'webhook_secret' => trim((string) ($config->pagamento_gateway_webhook_secret ?? '')),
            'sandbox' => (bool) ($config->pagamento_gateway_sandbox ?? true),
        ];
    }

    /** @return array<string, mixed> */
    public static function resumoAdmin(object $config): array
    {
        return [
            'pix_modo' => self::pixModo($config),
            'pix_modo_rotulo' => self::pixModosRotulos()[self::pixModo($config)] ?? self::pixModo($config),
            'pagamento_gateway' => self::provedor($config),
            'pagamento_gateway_rotulo' => self::provedor($config)
                ? (self::provedoresRotulos()[self::provedor($config)] ?? self::provedor($config))
                : null,
            'gateway_configurado' => self::gatewayConfigurado($config),
            'pagamento_online_ativo' => self::pagamentoOnlineAtivo($config),
            'usa_pix_automatico' => self::usaPixAutomatico($config),
            'pix_expiracao_minutos' => self::pixExpiracaoMinutos($config),
            'pagamento_gateway_token_configurado' => trim((string) ($config->pagamento_gateway_token ?? '')) !== '',
            'pagamento_gateway_public_key' => $config->pagamento_gateway_public_key ?? null,
            'pagamento_gateway_webhook_secret_configurado' => trim((string) ($config->pagamento_gateway_webhook_secret ?? '')) !== '',
            'pagamento_gateway_sandbox' => (bool) ($config->pagamento_gateway_sandbox ?? true),
            'webhook_url' => self::provedor($config)
                ? url('/api/integracoes/webhooks/'.self::provedor($config))
                : null,
        ];
    }
}
