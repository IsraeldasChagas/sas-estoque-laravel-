<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\IntegrationProviderInterface;
use App\Models\Integration;
use App\Models\IntegrationLog;
use App\Models\IntegrationMapping;
use App\Support\Integrations\IntegrationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Orquestrador central de integrações.
 */
final class IntegrationManager
{
    /** @var array<string, IntegrationProviderInterface> */
    private array $providers = [];

    public function register(IntegrationProviderInterface $provider): void
    {
        $this->providers[$provider->getProviderCode()] = $provider;
    }

    public function has(string $providerCode): bool
    {
        return isset($this->providers[$providerCode]);
    }

    public function get(string $providerCode): IntegrationProviderInterface
    {
        if (! $this->has($providerCode)) {
            throw new InvalidArgumentException("Provider de integração não registrado: {$providerCode}");
        }

        return $this->providers[$providerCode];
    }

    public function findOrCreateIntegration(string $providerCode, ?string $displayName = null): Integration
    {
        $provider = $this->get($providerCode);

        return Integration::query()->firstOrCreate(
            ['provider' => $providerCode, 'empresa_id' => null, 'unidade_id' => null],
            [
                'name' => $displayName ?? $provider->getDisplayName(),
                'environment' => 'homologation',
                'connection_status' => 'offline',
                'integration_status' => IntegrationStatus::NOT_CONFIGURED,
                'timeout_seconds' => 30,
                'retry_count' => 3,
                'is_active' => false,
            ]
        );
    }

    public function runtimeConfig(Integration $integration): IntegrationRuntimeConfig
    {
        return IntegrationRuntimeConfig::fromModel($integration);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveConfiguration(string $providerCode, array $data, bool $preserveSecretsWhenMasked = true): Integration
    {
        $integration = $this->findOrCreateIntegration($providerCode);
        $provider = $this->get($providerCode);

        $configForValidation = array_merge($integration->toProviderArray(), $data);
        if ($preserveSecretsWhenMasked) {
            if (isset($data['bearer_token']) && HttpIntegrationClient::isMaskedSecret($data['bearer_token'])) {
                unset($data['bearer_token']);
                $configForValidation['bearer_token'] = $integration->bearer_token;
            }
            if (isset($data['webhook_secret']) && HttpIntegrationClient::isMaskedSecret($data['webhook_secret'])) {
                unset($data['webhook_secret']);
                $configForValidation['webhook_secret'] = $integration->webhook_secret;
            }
        }

        $validation = $provider->validateConfiguration($configForValidation);
        if (! $validation['valid'] && ! empty($data['api_url']) && ! empty($data['bearer_token'] ?? $integration->bearer_token)) {
            // Permite salvar parcial se ainda não tem token/url — validação completa no teste
        }

        $integration->fill($data);
        $integration->integration_status = $this->resolveConfiguredStatus($integration);
        if (! $integration->is_active) {
            $integration->integration_status = IntegrationStatus::DISABLED;
        }
        $integration->save();

        Cache::forget("integration:{$providerCode}:health");

        return $integration->fresh();
    }

    /** @return array<string, mixed> */
    public function testConnection(string $providerCode, Integration $integration, ?Request $request = null, ?int $usuarioId = null): array
    {
        $provider = $this->get($providerCode);
        $runtime = $this->runtimeConfig($integration);
        $config = $runtime->toProviderArray();

        if (! $runtime->isActive) {
            return [
                'success' => false,
                'integration_status' => IntegrationStatus::DISABLED,
                'message' => 'Ative a integração antes de testar a conexão.',
            ];
        }

        $result = $provider->testConnection($config);

        $this->registrarLog(
            $integration,
            'test_connection',
            'GET',
            '/api/v1/integration/status',
            $result,
            $request,
            $usuarioId
        );

        $this->aplicarResultadoTeste($integration, $result);

        return $result;
    }

    /** @return array<string, mixed> */
    public function healthCheck(string $providerCode, ?Integration $integration = null, bool $live = true): array
    {
        $integration = $integration ?? $this->findOrCreateIntegration($providerCode);
        $runtime = $this->runtimeConfig($integration);

        if (! $live) {
            return $this->healthFromLocalState($integration);
        }

        $provider = $this->get($providerCode);
        $result = $provider->healthCheck($runtime->toProviderArray());

        $result['integration_id'] = $integration->id;
        $result['is_active'] = $integration->is_active;
        $result['last_successful_connection_at'] = $integration->last_successful_connection_at?->toIso8601String();
        $result['last_error'] = $integration->last_error;
        $result['consecutive_failures'] = $integration->consecutive_failures ?? 0;
        $result['last_response_time_ms'] = $integration->last_response_time_ms;
        $result['status_label'] = IntegrationStatus::label($result['integration_status'] ?? IntegrationStatus::NOT_CONFIGURED);

        Cache::put("integration:{$providerCode}:health", $result, now()->addMinutes(2));

        return $result;
    }

    public function clearCache(string $providerCode): void
    {
        Cache::forget("integration:{$providerCode}:health");
        Cache::forget("integration:{$providerCode}:config");
    }

    public function disconnect(Integration $integration, bool $clearMappings = false): Integration
    {
        $integration->update([
            'bearer_token' => null,
            'webhook_secret' => null,
            'is_active' => false,
            'connection_status' => 'offline',
            'integration_status' => IntegrationStatus::DISCONNECTED,
            'last_error' => null,
            'last_response_time_ms' => null,
            'api_version' => null,
            'empresa_external_id' => null,
            'empresa_external_name' => null,
            'consecutive_failures' => 0,
        ]);

        if ($clearMappings) {
            IntegrationMapping::query()->where('integration_id', $integration->id)->delete();
            $integration->update(['unidade_mappings' => []]);
        }

        $this->clearCache($integration->provider);

        return $integration->fresh();
    }

    /**
     * @param  list<array{local_id: string|int, external_id: string, external_name?: string, is_primary?: bool, is_active?: bool}>  $mappings
     */
    public function saveUnitMappings(Integration $integration, array $mappings): Integration
    {
        $jsonMap = [];
        foreach ($mappings as $row) {
            $localId = (string) ($row['local_id'] ?? '');
            $externalId = trim((string) ($row['external_id'] ?? ''));
            if ($localId === '' || $externalId === '') {
                continue;
            }

            $meta = [
                'external_name' => $row['external_name'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
            ];

            IntegrationMapping::query()->updateOrCreate(
                [
                    'integration_id' => $integration->id,
                    'entity_type' => 'unit',
                    'local_id' => $localId,
                ],
                [
                    'external_id' => $externalId,
                    'unidade_id' => (int) $localId,
                    'meta_json' => $meta,
                ]
            );

            $jsonMap[$localId] = [
                'external_id' => $externalId,
                ...$meta,
            ];
        }

        $integration->update(['unidade_mappings' => $jsonMap]);

        return $integration->fresh();
    }

    /** @return array<string, mixed> */
    public function sync(string $providerCode, Integration $integration): array
    {
        return $this->get($providerCode)->sync($this->runtimeConfig($integration)->toProviderArray());
    }

    /** @param array<string, mixed> $result */
    private function aplicarResultadoTeste(Integration $integration, array $result): void
    {
        $success = (bool) ($result['success'] ?? false);
        $integrationStatus = (string) ($result['integration_status'] ?? IntegrationStatus::CONNECTION_ERROR);

        $update = [
            'integration_status' => $integrationStatus,
            'connection_status' => $success ? 'online' : (($integrationStatus === 'authentication_error') ? 'error' : 'offline'),
            'last_response_time_ms' => $result['response_time_ms'] ?? null,
            'last_error' => $success ? null : ($result['message'] ?? 'Falha na conexão.'),
            'last_sync_at' => now(),
        ];

        if ($success) {
            $company = $result['company'] ?? [];
            $update['empresa_external_id'] = $company['id'] ?? $integration->empresa_external_id;
            $update['empresa_external_name'] = $company['name'] ?? $integration->empresa_external_name;
            $update['api_version'] = $result['api_version'] ?? $integration->api_version;
            $update['last_successful_connection_at'] = now();
            $update['consecutive_failures'] = 0;

            $configJson = $integration->config_json ?? [];
            $configJson['remote_environment'] = $result['environment'] ?? null;
            $configJson['last_test'] = [
                'at' => $result['tested_at'] ?? now()->toIso8601String(),
                'http_status' => $result['http_status'] ?? null,
            ];
            $update['config_json'] = $configJson;
        } else {
            $update['consecutive_failures'] = (int) ($integration->consecutive_failures ?? 0) + 1;
            if ($update['consecutive_failures'] >= 3) {
                $update['integration_status'] = IntegrationStatus::DEGRADED;
            }
        }

        $integration->update($update);
    }

    /** @param array<string, mixed> $result */
    private function registrarLog(
        Integration $integration,
        string $operation,
        string $method,
        string $endpoint,
        array $result,
        ?Request $request,
        ?int $usuarioId
    ): void {
        $success = (bool) ($result['success'] ?? false);

        IntegrationLog::query()->create([
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'operation' => $operation,
            'direction' => 'outbound',
            'http_method' => $method,
            'endpoint' => $endpoint,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'http_status' => $result['http_status'] ?? ($result['status'] ?? null),
            'status' => $success ? 'success' : 'error',
            'message' => $result['message'] ?? ($result['error']['message'] ?? null),
            'usuario_id' => $usuarioId,
            'ip' => $request?->ip(),
            'attempt_number' => $result['attempt'] ?? 1,
            'request_payload' => IntegrationLog::sanitizarPayload(['operation' => $operation]),
            'response_payload' => IntegrationLog::sanitizarPayload(
                is_array($result['raw'] ?? null) ? $result['raw'] : array_diff_key($result, array_flip(['bearer_token', 'webhook_secret']))
            ),
            'created_at' => now(),
        ]);
    }

    private function resolveConfiguredStatus(Integration $integration): string
    {
        if (! $integration->api_url || ! $integration->bearer_token) {
            return IntegrationStatus::NOT_CONFIGURED;
        }

        return IntegrationStatus::CONFIGURED;
    }

    /** @return array<string, mixed> */
    private function healthFromLocalState(Integration $integration): array
    {
        return [
            'provider' => $integration->provider,
            'integration_status' => $integration->integration_status ?? IntegrationStatus::NOT_CONFIGURED,
            'api_online' => $integration->connection_status === 'online',
            'is_active' => $integration->is_active,
            'environment' => $integration->environment,
            'api_version' => $integration->api_version,
            'company' => [
                'id' => $integration->empresa_external_id,
                'name' => $integration->empresa_external_name,
            ],
            'response_time_ms' => $integration->last_response_time_ms,
            'last_successful_connection_at' => $integration->last_successful_connection_at?->toIso8601String(),
            'last_error' => $integration->last_error,
            'consecutive_failures' => $integration->consecutive_failures ?? 0,
            'status_label' => IntegrationStatus::label($integration->integration_status ?? IntegrationStatus::NOT_CONFIGURED),
            'message' => 'Estado local (sem consulta ao vivo).',
        ];
    }

    /** @return array<string, mixed> */
    private function toProviderArray(Integration $integration): array
    {
        return $this->runtimeConfig($integration)->toProviderArray();
    }
}
