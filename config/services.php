<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
    ],

    'geocoding' => [
        'enabled' => env('GEOCODING_ENABLED', true),
        'url' => env('GEOCODING_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('GEOCODING_USER_AGENT', env('APP_NAME', 'Laravel').'/geocoder'),
    ],

    'native_push' => [
        'default' => env('NATIVE_PUSH_PROVIDER', 'null'),
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            'access_token' => env('FCM_ACCESS_TOKEN'),
        ],
        'apns' => [
            'endpoint' => env('APNS_ENDPOINT', 'https://api.push.apple.com'),
            'bundle_id' => env('APNS_BUNDLE_ID'),
            'bearer_token' => env('APNS_BEARER_TOKEN'),
        ],
        'unified' => [
            'enabled' => env('UNIFIED_PUSH_ENABLED', false),
            'allowed_hosts' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('UNIFIED_PUSH_ALLOWED_HOSTS', '')),
            ))),
        ],
        'gateway' => [
            'enabled' => env('PUSH_GATEWAY_ENABLED', false),
            'secret' => env('PUSH_GATEWAY_SECRET'),
        ],
    ],

    'ia' => [
        'openai' => [
            'token' => env('OPENAI_TOKEN'),
            'url' => 'https://api.openai.com/v1/chat/completions',
            'list_models' => 'https://api.openai.com/v1/models',
            'models' => [
                'gpt-4o-mini',
                'gpt-3.5-turbo',
                'gpt-4',
                'gpt-4-turbo',
                'gpt-4o',
            ],
        ],
        // 'gpt' => [ // Desabilitado pois é o mesmo que o openai,porem passando apenas o token, foi ajustado as referencias para o openai, caso queira usar o gpt, basta descomentar e ajustar as referencias no código.
        //     'token' => env('OPENAI_TOKEN')
        // ],
        'openrouter' => [
            'token' => env('OPENROUTER_TOKEN'),
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'list_models' => 'https://openrouter.ai/api/v1/models?max_price=0&sort=latency-low-to-high',
            'models' => [
                'sourceful/riverflow-v2.5-fast',
                'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
                'google/gemma-4-31b-it:free',
                'cognitivecomputations/dolphin-mistral-24b-venice-edition:free',
                'qwen/qwen3-next-80b-a3b-instruct:free',
                'google/gemma-3n-e2b-it:free',
                'allenai/molmo-2-8b:free',
                'arcee-ai/trinity-mini:free',
                'google/gemma-3-27b-it:free',
                'openai/gpt-oss-120b:free',
                'openai/gpt-oss-20b:free',
                'x-ai/grok-4-fast:free',
                'deepseek/deepseek-chat',
            ],
        ],
        'gemini' => [
            'token' => env('GEMINI_TOKEN'),
            'url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'list_models' => 'https://generativelanguage.googleapis.com/v1beta/openai/models',
            'models' => [
                'gemini-2.5-flash-lite',
                'gemini-3-flash-preview',
            ],
        ],
    ],

];
