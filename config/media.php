<?php

return [
    'disk' => env('MEDIA_DISK', 'media'),
    'archive_disk' => env('MEDIA_ARCHIVE_DISK', 'recordings'),
    'role' => env('MEDIA_NODE_ROLE', 'core'),
    'worker_id' => env('MEDIA_WORKER_ID', gethostname() ?: 'worker'),
    'worker_token' => env('MEDIA_WORKER_TOKEN'),
    'recordings_root' => env('MEDIA_RECORDINGS_PATH', storage_path('app/media-worker-recordings')),
    'core_url' => env('MEDIA_CORE_URL', env('APP_URL')),
    'core_verify_tls' => filter_var(env('MEDIA_CORE_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
    'core_connect_timeout' => (float) env('MEDIA_CORE_CONNECT_TIMEOUT', 10),
    'core_request_timeout' => (float) env('MEDIA_CORE_REQUEST_TIMEOUT', 30),

    'mediamtx' => [
        'api_url' => env('MEDIAMTX_API_URL', 'http://127.0.0.1:9997'),
        'api_token' => env('MEDIAMTX_API_TOKEN'),
        'public_hls_url' => env('MEDIAMTX_PUBLIC_HLS_URL', 'http://localhost:8888'),
        'public_rtmp_url' => env('MEDIAMTX_PUBLIC_RTMP_URL', 'rtmp://localhost:1935'),
        'connect_timeout' => (float) env('MEDIAMTX_CONNECT_TIMEOUT', 3),
        'timeout' => (float) env('MEDIAMTX_TIMEOUT', 10),
        'record_segment_duration' => env('MEDIAMTX_RECORD_SEGMENT_DURATION', '15m'),
    ],
];
