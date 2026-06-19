<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente OpenAI — chat com function calling e estimativa de custo.
 * Chave e modelo vêm do .env (OPENAI_API_KEY, OPENAI_MODEL).
 */
class OpenAiService
{
    private string $apiKey;

    private string $model;

    public function __construct()
    {
        $this->apiKey = trim((string) env('OPENAI_API_KEY', ''));
        $this->model = trim((string) env('OPENAI_MODEL', 'gpt-4o-mini')) ?: 'gpt-4o-mini';
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
    public function chat(array $messages, array $tools = []): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('OPENAI_API_KEY não configurada no servidor.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.35,
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
        $inPrice = (float) env('OPENAI_PRICE_INPUT_PER_1M', 0.15);
        $outPrice = (float) env('OPENAI_PRICE_OUTPUT_PER_1M', 0.60);

        return round(($inputTokens / 1_000_000) * $inPrice + ($outputTokens / 1_000_000) * $outPrice, 6);
    }

    /** Definições das ferramentas expostas à OpenAI (JSON Schema). */
    public static function toolDefinitions(): array
    {
        return [
            self::tool('consultar_produtos_abaixo_estoque_minimo', 'Lista produtos com estoque abaixo do mínimo.', []),
            self::tool('consultar_produto_por_nome', 'Busca produto pelo nome (parcial).', [
                'nome' => ['type' => 'string', 'description' => 'Nome ou parte do nome do produto'],
            ], ['nome']),
            self::tool('consultar_estoque_por_unidade', 'Resumo de estoque por unidade ou de uma unidade específica.', [
                'unidade_id' => ['type' => 'integer', 'description' => 'ID da unidade (opcional)'],
            ]),
            self::tool('consultar_movimentacoes_recentes', 'Últimas movimentações de estoque.', [
                'dias' => ['type' => 'integer', 'description' => 'Quantos dias atrás (padrão 7, máx 30)'],
                'unidade_id' => ['type' => 'integer', 'description' => 'Filtrar por unidade (opcional)'],
            ]),
            self::tool('consultar_vendas_do_dia', 'Faturamento/vendas do fechamento de caixa em uma data.', [
                'data' => ['type' => 'string', 'description' => 'Data YYYY-MM-DD (padrão hoje)'],
                'unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)'],
            ]),
            self::tool('consultar_compras_recentes', 'Listas de compras recentes.', [
                'limite' => ['type' => 'integer', 'description' => 'Quantidade máxima (padrão 10)'],
            ]),
            self::tool('consultar_fornecedores', 'Lista fornecedores cadastrados.', [
                'busca' => ['type' => 'string', 'description' => 'Filtrar por nome (opcional)'],
            ]),
            self::tool('consultar_logs_recentes', 'Logs de auditoria recentes (requer permissão).', [
                'limite' => ['type' => 'integer', 'description' => 'Máximo de registros (padrão 20)'],
            ]),
            self::tool('consultar_resumo_financeiro', 'Resumo financeiro do período (faturamento, CMV/custos).', [
                'de' => ['type' => 'string', 'description' => 'Data início YYYY-MM-DD'],
                'ate' => ['type' => 'string', 'description' => 'Data fim YYYY-MM-DD'],
                'unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)'],
            ]),
            self::tool('consultar_manual_documentacao', 'Busca no manual e documentos internos cadastrados.', [
                'consulta' => ['type' => 'string', 'description' => 'Termo ou pergunta sobre procedimento'],
            ], ['consulta']),
        ];
    }

    /** @param  array<string, array<string, mixed>>  $props
     * @param  string[]  $required
     */
    private static function tool(string $name, string $description, array $props, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $props,
                    'required' => $required,
                ],
            ],
        ];
    }
}
