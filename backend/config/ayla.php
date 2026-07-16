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

    'enabled' => filter_var(env('AYLA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'read_only' => filter_var(env('AYLA_READ_ONLY', true), FILTER_VALIDATE_BOOLEAN),

    'token' => env('AYLA_SAS_TOKEN', ''),

    'rate_limit' => max(1, (int) env('AYLA_RATE_LIMIT', 60)),

    'allowed_units' => $unidades,

    // Bot Telegram para links de convite (https://t.me/{username}?start=TOKEN)
    'telegram_bot_username' => ltrim(trim((string) env('AYLA_TELEGRAM_BOT_USERNAME', 'AylaSaborPsraenseBot')), '@'),

    // Token dedicado ao Telegram Auth Bridge (separado do AYLA_SAS_TOKEN)
    'bridge_token' => env('AYLA_BRIDGE_TOKEN', ''),

    // API interna na VPS para sincronizar allowlist do OpenClaw
    'vps_sync_url' => rtrim(trim((string) env('AYLA_VPS_SYNC_URL', '')), '/'),
    'vps_sync_token' => env('AYLA_VPS_SYNC_TOKEN', ''),

    'convite_validade_horas' => max(1, (int) env('AYLA_CONVITE_VALIDADE_HORAS', 24)),
];
