<?php

namespace App\Services\Fiscal;

use Illuminate\Support\Facades\Http;

final class FocusNfeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeoutSeconds = 45,
    ) {}

    /** @return array<string, mixed> */
    public function enviarNfce(string $ref, array $payload): array
    {
        return $this->request('POST', '/v2/nfce?ref='.rawurlencode($ref), $payload);
    }

    /** @return array<string, mixed> */
    public function consultarNfce(string $ref): array
    {
        return $this->request('GET', '/v2/nfce/'.rawurlencode($ref), null);
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?array $body): array
    {
        $url = rtrim($this->baseUrl, '/').$path;
        $pending = Http::withBasicAuth($this->token, '')
            ->acceptJson()
            ->timeout($this->timeoutSeconds);

        $response = $method === 'GET'
            ? $pending->get($url)
            : $pending->post($url, $body ?? []);

        $json = $response->json();
        if (! is_array($json)) {
            $json = ['raw' => $response->body()];
        }

        return [
            'http_status' => $response->status(),
            'body' => $json,
            'ok' => $response->successful(),
        ];
    }
}
