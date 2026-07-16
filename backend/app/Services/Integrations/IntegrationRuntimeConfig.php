<?php

namespace App\Services\Integrations;

use App\Models\Integration;

/**
 * Configuração em memória com credenciais descriptografadas (uso interno).
 */
final class IntegrationRuntimeConfig
{
    public function __construct(
        public readonly Integration $integration,
        public readonly string $provider,
        public readonly ?string $apiUrl,
        public readonly ?string $bearerToken,
        public readonly ?string $webhookSecret,
        public readonly string $environment,
        public readonly int $timeoutSeconds,
        public readonly int $retryCount,
        public readonly bool $isActive,
        public readonly array $configJson,
        public readonly array $enabledResources,
    ) {}

    public static function fromModel(Integration $integration): self
    {
        return new self(
            integration: $integration,
            provider: $integration->provider,
            apiUrl: $integration->api_url,
            bearerToken: $integration->bearer_token,
            webhookSecret: $integration->webhook_secret,
            environment: $integration->environment ?? 'homologation',
            timeoutSeconds: (int) ($integration->timeout_seconds ?? 30),
            retryCount: (int) ($integration->retry_count ?? 3),
            isActive: (bool) $integration->is_active,
            configJson: $integration->config_json ?? [],
            enabledResources: $integration->enabled_resources ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toProviderArray(): array
    {
        return [
            'id' => $this->integration->id,
            'provider' => $this->provider,
            'api_url' => $this->apiUrl,
            'bearer_token' => $this->bearerToken,
            'webhook_secret' => $this->webhookSecret,
            'environment' => $this->environment,
            'timeout_seconds' => $this->timeoutSeconds,
            'retry_count' => $this->retryCount,
            'is_active' => $this->isActive,
            'config_json' => $this->configJson,
            'enabled_resources' => $this->enabledResources,
            'empresa_external_id' => $this->integration->empresa_external_id,
            'empresa_external_name' => $this->integration->empresa_external_name,
            'api_version' => $this->integration->api_version,
        ];
    }
}
