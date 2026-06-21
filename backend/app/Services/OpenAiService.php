<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente OpenAI — chat com function calling e estimativa de custo.
 * Chave e modelo vêm de config/openai.php (lê .env).
 */
class OpenAiService
{
    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiKey = trim((string) self::cfg('api_key'));
        $this->model = trim((string) self::cfg('model', 'gpt-4o-mini')) ?: 'gpt-4o-mini';
    }

    /** Lê config/openai.php ou config/services.php (fallback). */
    private static function cfg(string $key, mixed $default = ''): mixed
    {
        $v = config("openai.{$key}");
        if ($v !== null && $v !== '') {
            return $v;
        }
        $v = config("services.openai.{$key}");
        if ($v !== null && $v !== '') {
            return $v;
        }

        return $default;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{message: array<string, mixed>, usage: array<string, int>, cost: float}
     */
    public function chat(array $messages, array $tools = [], ?float $temperature = null): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('OPENAI_API_KEY não configurada no servidor.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature ?? 0.35,
            'max_tokens' => 1200,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $resp = Http::timeout(120)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $resp->successful()) {
            $body = $resp->json();
            $msg = is_array($body) ? ($body['error']['message'] ?? $resp->body()) : $resp->body();
            throw new \RuntimeException('OpenAI: '.mb_substr((string) $msg, 0, 400));
        }

        $data = $resp->json();
        $usage = [
            'prompt_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
        ];

        return [
            'message' => $data['choices'][0]['message'] ?? [],
            'usage' => $usage,
            'cost' => $this->estimateCost($usage['prompt_tokens'], $usage['completion_tokens']),
        ];
    }

    /** Estimativa aproximada em USD (ajustável via env). */
    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $inPrice = (float) self::cfg('price_input_per_1m', 0.15);
        $outPrice = (float) self::cfg('price_output_per_1m', 0.60);

        return round(($inputTokens / 1_000_000) * $inPrice + ($outputTokens / 1_000_000) * $outPrice, 6);
    }

    /** Definições das ferramentas expostas à OpenAI (JSON Schema). */
    public static function toolDefinitions(): array
    {
        return \App\Support\SasIa\SasIaToolRegistry::definitions();
    }
}
