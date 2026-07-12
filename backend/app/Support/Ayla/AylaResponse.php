<?php

namespace App\Support\Ayla;

use Illuminate\Http\JsonResponse;

/**
 * Envelope padronizado das respostas da Ayla.
 * Nunca inclui stack trace, SQL, token, senha, chave API ou caminho interno.
 */
class AylaResponse
{
    /** @param array<string, mixed> $data */
    public static function success(string $acao, string $message, array $data = [], array $extraMeta = [], int $status = 200): JsonResponse
    {
        return self::withCors(response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'acao' => $acao,
                'timestamp' => now()->toIso8601String(),
                'read_only' => AylaSettings::somenteLeitura(),
            ], $extraMeta),
        ], $status));
    }

    public static function error(string $acao, string $message, string $code, int $status = 400, array $extraMeta = []): JsonResponse
    {
        return self::withCors(response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
            'meta' => array_merge([
                'acao' => $acao,
                'code' => $code,
                'timestamp' => now()->toIso8601String(),
            ], $extraMeta),
        ], $status));
    }

    private static function withCors(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Usuario-Id, X-Ayla-Channel, X-Ayla-Sender-Id');

        return $response;
    }
}
