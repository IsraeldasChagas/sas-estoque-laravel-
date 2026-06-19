<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini') ?: 'gpt-4o-mini',
        'price_input_per_1m' => (float) env('OPENAI_PRICE_INPUT_PER_1M', 0.15),
        'price_output_per_1m' => (float) env('OPENAI_PRICE_OUTPUT_PER_1M', 0.60),
        'limit_usuario' => (int) env('SAS_IA_LIMIT_USUARIO', 20),
        'limit_gestor' => (int) env('SAS_IA_LIMIT_GESTOR', 100),
        'limit_admin' => (int) env('SAS_IA_LIMIT_ADMIN', 300),
    ],

];
