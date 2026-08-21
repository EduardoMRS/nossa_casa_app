<?php

return [
    'cdn_url' => env('BIBLE_API_CDN_URL', 'https://cdn.jsdelivr.net/gh/wldeh/bible-api/bibles'),
    'repository_contents_url' => env('BIBLE_API_CONTENTS_URL', 'https://api.github.com/repos/wldeh/bible-api/contents/bibles'),
    'timeout' => (float) env('BIBLE_API_TIMEOUT', 10),
    'cache_ttl' => (int) env('BIBLE_API_CACHE_TTL', 86400),
    'default_versions' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BIBLE_DEFAULT_VERSIONS', 'pt-almeida-1911,pt-BR-blt,en-kjv')),
    ))),
    'offline_versions' => [
        'pt-almeida-1911' => [
            'name' => 'Almeida 1911',
            'abbreviation' => 'JFA 1911',
            'language' => 'Portuguese',
            'language_code' => 'por',
            'scope' => 'Bible',
            'copyright_key' => 'bible.licenses.get_bible_public_domain',
            'source_url' => env('BIBLE_ALMEIDA_1911_URL', 'https://api.getbible.net/v2/almeida.json'),
        ],
        'en-kjv' => [
            'name' => 'King James Version',
            'abbreviation' => 'KJV',
            'language' => 'English',
            'language_code' => 'eng',
            'scope' => 'Bible',
            'copyright_key' => 'bible.licenses.get_bible_public_domain',
            'source_url' => env('BIBLE_KJV_URL', 'https://api.getbible.net/v2/kjv.json'),
        ],
    ],
];
