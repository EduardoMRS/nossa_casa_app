<?php

return [
    'instance_id' => env('NATIVE_INSTANCE_ID'),
    'instance_name' => env('NATIVE_INSTANCE_NAME', env('APP_NAME', 'Nossa Casa')),
    'base_url' => env('NATIVE_BASE_URL', env('APP_URL', 'http://localhost')),
    'protocol_version' => 1,
    'api_version' => 1,
    'minimum_app_version' => env('NATIVE_MINIMUM_APP_VERSION', '1.0.0'),
    'privacy_url' => env('NATIVE_PRIVACY_URL'),
    'terms_url' => env('NATIVE_TERMS_URL'),
    'capabilities' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NATIVE_CAPABILITIES', 'posts,events,bible,live_streams,realtime,native_push')),
    ))),
    'realtime' => [
        'enabled' => env('NATIVE_REALTIME_ENABLED', true),
        'key' => env('REVERB_APP_KEY'),
        'host' => env('NATIVE_REALTIME_HOST', env('VITE_REVERB_HOST')),
        'port' => (int) env('NATIVE_REALTIME_PORT', env('VITE_REVERB_PORT', 443)),
        'scheme' => env('NATIVE_REALTIME_SCHEME', env('VITE_REVERB_SCHEME', 'https')),
        'auth_path' => '/api/broadcasting/auth',
    ],
];
