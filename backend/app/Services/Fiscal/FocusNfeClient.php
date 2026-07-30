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

    /** @return array{http_status: int, body: string, content_type: string|null, ok: bool} */
    public function baixarNfcePdf(string $ref): array
    {
        return $this->requestBinary('GET', '/v2/nfce/'.rawurlencode($ref).'.pdf');
    }

    /** @return array{http_status: int, body: string, content_type: string|null, ok: bool} */
    public function baixarNfceXml(string $ref): array
    {
        return $this->requestBinary('GET', '/v2/nfce/'.rawurlencode($ref).'.xml');
    }

    /** @return array{http_status: int, body: string, content_type: string|null, ok: bool} */
    public function baixarUrl(string $url): array
    {
        $response = Http::withBasicAuth($this->token, '')
            ->timeout($this->timeoutSeconds)
            ->get($url);

        return [
            'http_status' => $response->status(),
            'body' => (string) $response->body(),
            'content_type' => $response->header('Content-Type'),
            'ok' => $response->successful(),
        ];
    }

    /** @return array{http_status: int, body: string, content_type: string|null, ok: bool} */
    private function requestBinary(string $method, string $path): array
    {
        $url = rtrim($this->baseUrl, '/').$path;
        $response = Http::withBasicAuth($this->token, '')
            ->timeout($this->timeoutSeconds)
            ->get($url);

        return [
            'http_status' => $response->status(),
            'body' => (string) $response->body(),
            'content_type' => $response->header('Content-Type'),
            'ok' => $response->successful(),
        ];
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
