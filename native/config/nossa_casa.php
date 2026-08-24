<?php

return [
    'default_server' => env('NOSSA_CASA_DEFAULT_SERVER', 'https://nossacasa.app'),
    'discovery_timeout' => (int) env('NOSSA_CASA_DISCOVERY_TIMEOUT', 10),
    'request_timeout' => (int) env('NOSSA_CASA_REQUEST_TIMEOUT', 20),
    'cache_ttl' => (int) env('NOSSA_CASA_CACHE_TTL', 3600),
];
