<?php

namespace App\Services\Integrations;

use App\Support\Integrations\IntegrationErrorMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cliente HTTP compartilhado para integrações externas.
 */
final class HttpIntegrationClient
{
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly int $retryCount = 3,
        private readonly string $apiVersion = 'v1',
        private readonly ?string $bearerToken = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options  body, query, headers
     * @return array{success: bool, status: int|null, data: mixed, error: array{code: string, message: string}|null, response_time_ms: int}
     */
    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, status: int|null, data: mixed, error: array{code: string, message: string}|null, response_time_ms: int}
     */
    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, status: int|null, data: mixed, error: array{code: string, message: string}|null, response_time_ms: int}
     */
    public function put(string $url, array $options = []): array
    {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, status: int|null, data: mixed, error: array{code: string, message: string}|null, response_time_ms: int}
     */
    public function patch(string $url, array $options = []): array
    {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, status: int|null, data: mixed, error: array{code: string, message: string}|null, response_time_ms: int}
     */
    public function delete(string $url, array $options = []): array
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, status: int|null, data: mixed, error: array{code: string, message: string}|null, response_time_ms: int}
     */
    public function request(string $method, string $url, array $options = []): array
    {
        $started = microtime(true);
        $attempts = max(1, $this->retryCount + 1);
        $lastResult = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $pending = $this->buildPendingRequest($options);
                $response = $pending->send(strtoupper($method), $url, $this->buildSendOptions($options));
                $elapsed = (int) round((microtime(true) - $started) * 1000);

                $status = $response->status();
                $body = $response->body();

                if ($body === '' || $body === null) {
                    $data = null;
                } else {
                    $decoded = json_decode($body, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return [
                            'success' => false,
                            'status' => $status,
                            'data' => null,
                            'error' => IntegrationErrorMapper::invalidJson(),
                            'response_time_ms' => $elapsed,
                            'attempt' => $attempt,
                        ];
                    }
                    $data = $decoded;
                }

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'status' => $status,
                        'data' => $data,
                        'error' => null,
                        'response_time_ms' => $elapsed,
                        'attempt' => $attempt,
                    ];
                }

                $err = IntegrationErrorMapper::fromHttpStatus($status, is_array($data) ? ($data['message'] ?? null) : null);
                $lastResult = [
                    'success' => false,
                    'status' => $status,
                    'data' => $data,
                    'error' => $err,
                    'response_time_ms' => $elapsed,
                    'attempt' => $attempt,
                ];

                if (! $this->shouldRetry($status) || $attempt >= $attempts) {
                    return $lastResult;
                }
            } catch (ConnectionException $e) {
                $elapsed = (int) round((microtime(true) - $started) * 1000);
                $err = IntegrationErrorMapper::fromThrowable($e);
                $lastResult = [
                    'success' => false,
                    'status' => null,
                    'data' => null,
                    'error' => $err,
                    'response_time_ms' => $elapsed,
                    'attempt' => $attempt,
                ];
                if ($attempt >= $attempts) {
                    return $lastResult;
                }
            } catch (\Throwable $e) {
                $elapsed = (int) round((microtime(true) - $started) * 1000);

                return [
                    'success' => false,
                    'status' => null,
                    'data' => null,
                    'error' => IntegrationErrorMapper::fromThrowable($e),
                    'response_time_ms' => $elapsed,
                    'attempt' => $attempt,
                ];
            }

            usleep(200000 * $attempt);
        }

        return $lastResult ?? [
            'success' => false,
            'status' => null,
            'data' => null,
            'error' => ['code' => 'UNKNOWN', 'message' => 'Não foi possível concluir a operação.'],
            'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            'attempt' => $attempts,
        ];
    }

    /** @param array<string, mixed> $options */
    private function buildPendingRequest(array $options): PendingRequest
    {
        $headers = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Integration-Client' => 'SAS-Estoque',
            'X-Api-Version' => $this->apiVersion,
        ], $options['headers'] ?? []);

        $pending = Http::timeout($this->timeoutSeconds)->withHeaders($headers);

        if ($this->bearerToken) {
            $pending = $pending->withToken($this->bearerToken);
        }

        return $pending;
    }

    /** @param array<string, mixed> $options */
    private function buildSendOptions(array $options): array
    {
        $send = [];
        if (isset($options['query'])) {
            $send['query'] = $options['query'];
        }
        if (isset($options['body'])) {
            $send['json'] = $options['body'];
        } elseif (isset($options['json'])) {
            $send['json'] = $options['json'];
        }

        return $send;
    }

    private function shouldRetry(?int $status): bool
    {
        if ($status === null) {
            return true;
        }

        return $status >= 500 && $status <= 599;
    }

    public static function isMaskedSecret(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return Str::contains($value, '•') || Str::contains($value, '****');
    }
}
