<?php

/**
 * Ayla — API dedicada somente leitura (Telegram/OpenClaw -> MCP -> /api/ayla/v1).
 * A Ayla nunca acessa banco, models ou rotas internas diretamente; só esta API.
 */

$unidades = array_values(array_filter(array_map(
    static fn ($v) => (int) trim((string) $v),
    explode(',', (string) env('AYLA_ALLOWED_UNITS', ''))
), static fn ($v) => $v > 0));

return [
    'version' => 'v1',

    // Integração pode ser desativada sem remover código (retorna 503).
    'enabled' => filter_var(env('AYLA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Modo somente leitura ativo por padrão. Esta versão não implementa escrita.
    'read_only' => filter_var(env('AYLA_READ_ONLY', true), FILTER_VALIDATE_BOOLEAN),

    // Token Bearer esperado. Nunca é retornado em respostas nem gravado em logs.
    'token' => env('AYLA_SAS_TOKEN', ''),

    // Requisições por minuto por combinação token+IP.
    'rate_limit' => max(1, (int) env('AYLA_RATE_LIMIT', 60)),

    // Unidades permitidas quando não há usuário identificado. Vazio = todas.
    'allowed_units' => $unidades,
];
