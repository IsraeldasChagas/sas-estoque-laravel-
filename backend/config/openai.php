<?php

/**
 * SAS IA / OpenAI — valores lidos do .env.
 * Em produção use config() no código (não env() direto), pois com config:cache o env() fora dos arquivos config retorna vazio.
 */
return [
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini') ?: 'gpt-4o-mini',
    'price_input_per_1m' => (float) env('OPENAI_PRICE_INPUT_PER_1M', 0.15),
    'price_output_per_1m' => (float) env('OPENAI_PRICE_OUTPUT_PER_1M', 0.60),
    'limit_usuario' => (int) env('SAS_IA_LIMIT_USUARIO', 20),
    'limit_gestor' => (int) env('SAS_IA_LIMIT_GESTOR', 100),
    'limit_admin' => (int) env('SAS_IA_LIMIT_ADMIN', 300),
];
