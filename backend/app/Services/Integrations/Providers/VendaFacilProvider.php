<?php

namespace App\Services\Integrations\Providers;

use App\Contracts\Integrations\IntegrationProviderInterface;
use App\Services\Integrations\HttpIntegrationClient;
use App\Support\Integrations\IntegrationUrlValidator;

/**
 * Provider VendaFácil — Fase 2: conexão real via GET /api/v1/integration/status
 */
final class VendaFacilProvider implements IntegrationProviderInterface
{
    public const CODE = 'vendafacil';

    public const STATUS_PATH = '/integration/status';

    public const UNITS_PATH = '/units';

    public function getProviderCode(): string
    {
        return self::CODE;
    }

    public function providerName(): string
    {
        return self::CODE;
    }

    public function getDisplayName(): string
    {
        return 'VendaFácil';
    }

    public function getAvailableResources(): array
    {
        return [
            'produtos' => 'Produtos comerciais',
            'estoque' => 'Disponibilidade de estoque',
            'clientes' => 'Clientes',
            'pedidos' => 'Pedidos',
            'delivery' => 'Delivery',
            'vendas' => 'Vendas',
            'caixa' => 'Caixa',
            'fiscal' => 'Fiscal',
            'pagamentos' => 'Formas de pagamento',
        ];
    }

    public function validateConfiguration(array $config): array
    {
        $errors = [];
        $url = trim((string) ($config['api_url'] ?? ''));
        if ($url === '') {
            $errors[] = 'Informe a URL base da API.';
        } else {
            try {
                IntegrationUrlValidator::validate($url, (string) ($config['environment'] ?? 'homologation'));
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $token = trim((string) ($config['bearer_token'] ?? ''));
        if ($token === '') {
            $errors[] = 'Informe o Bearer Token.';
        }

        $timeout = (int) ($config['timeout_seconds'] ?? 30);
        if ($timeout < 3 || $timeout > 60) {
            $errors[] = 'Timeout deve estar entre 3 e 60 segundos.';
        }

        $retry = (int) ($config['retry_count'] ?? 3);
        if ($retry < 0 || $retry > 5) {
            $errors[] = 'Número de tentativas deve estar entre 0 e 5.';
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    public function testConnection(array $config): array
    {
        $validation = $this->validateConfiguration($config);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'integration_status' => 'connection_error',
                'message' => $validation['errors'][0] ?? 'Configuração inválida.',
                'errors' => $validation['errors'],
            ];
        }

        $client = $this->buildClient($config);
        $url = $this->buildApiUrl((string) $config['api_url'], self::STATUS_PATH);
        $response = $client->get($url);

        if (! $response['success']) {
            $status = $response['status'];
            $integrationStatus = ($status === 401) ? 'authentication_error' : 'connection_error';

            return [
                'success' => false,
                'integration_status' => $integrationStatus,
                'connection_status' => 'offline',
                'http_status' => $status,
                'response_time_ms' => $response['response_time_ms'],
                'message' => $response['error']['message'] ?? 'Falha na conexão.',
                'error' => $response['error'],
            ];
        }

        $parsed = $this->parseStatusPayload($response['data']);
        if (! $parsed['company_id']) {
            return [
                'success' => false,
                'integration_status' => 'connection_error',
                'connection_status' => 'error',
                'http_status' => $response['status'],
                'response_time_ms' => $response['response_time_ms'],
                'message' => 'A API respondeu, mas não identificou a empresa.',
                'raw' => $this->safeRaw($response['data']),
            ];
        }

        return [
            'success' => true,
            'integration_status' => 'connected',
            'connection_status' => 'online',
            'http_status' => $response['status'],
            'response_time_ms' => $response['response_time_ms'],
            'message' => 'Conexão estabelecida com sucesso.',
            'company' => [
                'id' => $parsed['company_id'],
                'name' => $parsed['company_name'],
            ],
            'environment' => $parsed['environment'],
            'api_version' => $parsed['api_version'],
            'remote' => $parsed,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    public function healthCheck(array $config): array
    {
        if (empty($config['api_url']) || empty($config['bearer_token'])) {
            return [
                'provider' => self::CODE,
                'integration_status' => empty($config['api_url']) && empty($config['bearer_token'])
                    ? 'not_configured'
                    : 'configured',
                'api_online' => false,
                'token_valid' => null,
                'message' => 'Integração não configurada completamente.',
            ];
        }

        if (empty($config['is_active'])) {
            return [
                'provider' => self::CODE,
                'integration_status' => 'disabled',
                'api_online' => false,
                'token_valid' => null,
                'message' => 'Integração desativada.',
            ];
        }

        $test = $this->testConnection($config);

        return [
            'provider' => self::CODE,
            'integration_status' => $test['integration_status'] ?? 'connection_error',
            'api_online' => (bool) ($test['success'] ?? false),
            'token_valid' => ($test['http_status'] ?? null) !== 401,
            'environment' => $test['environment'] ?? $config['environment'] ?? null,
            'api_version' => $test['api_version'] ?? $config['api_version'] ?? null,
            'company' => $test['company'] ?? [
                'id' => $config['empresa_external_id'] ?? null,
                'name' => $config['empresa_external_name'] ?? null,
            ],
            'response_time_ms' => $test['response_time_ms'] ?? null,
            'http_status' => $test['http_status'] ?? null,
            'message' => $test['message'] ?? null,
            'last_checked_at' => now()->toIso8601String(),
        ];
    }

    public function getRemoteCompany(array $config): ?array
    {
        $test = $this->testConnection($config);
        if (! ($test['success'] ?? false)) {
            return null;
        }

        return $test['company'] ?? null;
    }

    public function getRemoteEnvironment(array $config): ?string
    {
        $test = $this->testConnection($config);
        if ($test['success'] ?? false) {
            return $test['environment'] ?? null;
        }

        return $config['environment'] ?? null;
    }

    public function getRemoteVersion(array $config): ?string
    {
        $test = $this->testConnection($config);
        if ($test['success'] ?? false) {
            return $test['api_version'] ?? null;
        }

        return $config['api_version'] ?? null;
    }

    public function sync(array $config): array
    {
        return [
            'success' => false,
            'status' => 'skipped',
            'message' => 'Sincronização operacional não disponível na Fase 2.',
        ];
    }

    /**
     * Consulta opcional de unidades remotas.
     *
     * @param  array<string, mixed>  $config
     * @return array{success: bool, units: list<array<string, mixed>>, error: array<string, string>|null}
     */
    public function fetchRemoteUnits(array $config): array
    {
        $validation = $this->validateConfiguration($config);
        if (! $validation['valid']) {
            return ['success' => false, 'units' => [], 'error' => ['code' => 'INVALID_CONFIG', 'message' => $validation['errors'][0]]];
        }

        $client = $this->buildClient($config);
        $url = $this->buildApiUrl((string) $config['api_url'], self::UNITS_PATH);
        $response = $client->get($url);

        if (! $response['success']) {
            return [
                'success' => false,
                'units' => [],
                'error' => $response['error'],
            ];
        }

        $data = $response['data'];
        $units = [];
        $list = is_array($data) ? ($data['data'] ?? $data['units'] ?? $data) : [];
        if (is_array($list)) {
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $units[] = [
                    'id' => (string) ($item['id'] ?? $item['external_id'] ?? ''),
                    'name' => (string) ($item['name'] ?? $item['nome'] ?? ''),
                ];
            }
        }

        return ['success' => true, 'units' => $units, 'error' => null];
    }

    /** Monta URL absoluta a partir da base configurada (ex.: …/api/v1 + /integration/status). */
    private function buildApiUrl(string $apiUrl, string $relativePath): string
    {
        $base = rtrim(trim($apiUrl), '/');
        $path = '/'.ltrim($relativePath, '/');

        if (str_ends_with(strtolower($base), '/api/v1')) {
            return $base.$path;
        }

        return $base.'/api/v1'.$path;
    }

    /** @param array<string, mixed> $config */
    private function buildClient(array $config): HttpIntegrationClient
    {
        return new HttpIntegrationClient(
            timeoutSeconds: (int) ($config['timeout_seconds'] ?? 30),
            retryCount: (int) ($config['retry_count'] ?? 3),
            apiVersion: (string) (($config['config_json']['api_version'] ?? null) ?: 'v1'),
            bearerToken: $config['bearer_token'] ?? null,
        );
    }

    /**
     * @return array{company_id: ?string, company_name: ?string, environment: ?string, api_version: ?string}
     */
    private function parseStatusPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return ['company_id' => null, 'company_name' => null, 'environment' => null, 'api_version' => null];
        }

        $data = $payload['data'] ?? $payload;
        if (! is_array($data)) {
            return ['company_id' => null, 'company_name' => null, 'environment' => null, 'api_version' => null];
        }

        $company = $data['company'] ?? $data['empresa'] ?? null;
        $companyId = null;
        $companyName = null;

        if (is_array($company)) {
            $companyId = isset($company['id']) ? (string) $company['id'] : null;
            $companyName = $company['name'] ?? $company['nome'] ?? null;
        }

        if (! $companyId && isset($data['company_id'])) {
            $companyId = (string) $data['company_id'];
        }
        if (! $companyId && isset($data['empresa_id'])) {
            $companyId = (string) $data['empresa_id'];
        }
        if (! $companyName && isset($data['company_name'])) {
            $companyName = (string) $data['company_name'];
        }

        return [
            'company_id' => $companyId,
            'company_name' => $companyName ? (string) $companyName : null,
            'environment' => isset($data['environment']) ? (string) $data['environment'] : ($data['ambiente'] ?? null),
            'api_version' => isset($data['api_version']) ? (string) $data['api_version'] : ($data['version'] ?? null),
        ];
    }

    private function safeRaw(mixed $data): mixed
    {
        if (! is_array($data)) {
            return null;
        }
        unset($data['token'], $data['bearer_token'], $data['secret']);

        return $data;
    }
}
