<?php

return [
    'cdn_url' => env('BIBLE_API_CDN_URL', 'https://cdn.jsdelivr.net/gh/wldeh/bible-api/bibles'),
    'repository_contents_url' => env('BIBLE_API_CONTENTS_URL', 'https://api.github.com/repos/wldeh/bible-api/contents/bibles'),
    'timeout' => (float) env('BIBLE_API_TIMEOUT', 10),
    'cache_ttl' => (int) env('BIBLE_API_CACHE_TTL', 86400),
    'default_versions' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BIBLE_DEFAULT_VERSIONS', 'pt-br-nvi,pt-br-nvt,pt-almeida-1911,pt-BR-blt')),
    ))),
    'online_versions' => [
        'pt-br-nvi' => [
            'name' => 'Nova Versão Internacional',
            'abbreviation' => 'NVI',
            'language' => 'Portuguese',
            'language_code' => 'por',
            'scope' => 'Bible',
            'copyright_key' => 'bible.licenses.nvi_online_only',
            'source_url' => env('BIBLE_NVI_URL', 'https://raw.githubusercontent.com/MaatheusGois/bible/main/versions/pt-br/nvi.json'),
        ],
        'pt-br-nvt' => [
            'name' => 'Nova Versão Transformadora',
            'abbreviation' => 'NVT',
            'language' => 'Portuguese',
            'language_code' => 'por',
            'scope' => 'Bible',
            'copyright_key' => 'bible.licenses.nvt_online_only',
            'source_url' => env('BIBLE_NVT_URL', 'https://raw.githubusercontent.com/barretogustavo/smart-bible-versions/main/NVT%20-%20Nova%20Vers%C3%A3o%20Transformadora.json'),
            'book_names' => [
                'Gênesis', 'Êxodo', 'Levítico', 'Números', 'Deuteronômio', 'Josué', 'Juízes', 'Rute',
                '1 Samuel', '2 Samuel', '1 Reis', '2 Reis', '1 Crônicas', '2 Crônicas', 'Esdras', 'Neemias',
                'Ester', 'Jó', 'Salmos', 'Provérbios', 'Eclesiastes', 'Cântico dos Cânticos', 'Isaías', 'Jeremias',
                'Lamentações', 'Ezequiel', 'Daniel', 'Oseias', 'Joel', 'Amós', 'Obadias', 'Jonas', 'Miqueias',
                'Naum', 'Habacuque', 'Sofonias', 'Ageu', 'Zacarias', 'Malaquias', 'Mateus', 'Marcos', 'Lucas',
                'João', 'Atos', 'Romanos', '1 Coríntios', '2 Coríntios', 'Gálatas', 'Efésios', 'Filipenses',
                'Colossenses', '1 Tessalonicenses', '2 Tessalonicenses', '1 Timóteo', '2 Timóteo', 'Tito',
                'Filemom', 'Hebreus', 'Tiago', '1 Pedro', '2 Pedro', '1 João', '2 João', '3 João', 'Judas',
                'Apocalipse',
            ],
        ],
    ],
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
