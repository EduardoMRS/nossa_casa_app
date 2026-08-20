<?php

return [
    'units' => [
        'headquarters' => [
            'default' => 'church',
            'options' => [
                'church',
                'temple',
                'congregation',
                'parish',
                'community',
                'ministry',
                'headquarters',
                'campus',
            ],
        ],
        'branch' => [
            'default' => 'branch',
            'options' => [
                'branch',
                'church',
                'temple',
                'congregation',
                'parish',
                'community',
                'ministry',
                'campus',
                'extension',
                'chapel',
                'local_church',
            ],
        ],
    ],
    'roles' => [
        'guest' => [
            'default' => 'visitor',
            'options' => ['visitor', 'guest'],
        ],
        'member' => [
            'default' => 'member',
            'options' => ['member', 'disciple', 'associate'],
        ],
        'leader' => [
            'default' => 'ministry_leader',
            'options' => ['leader', 'ministry_leader', 'coordinator', 'deacon'],
        ],
        'media' => [
            'default' => 'media_team',
            'options' => ['media', 'media_team', 'communications', 'creative_team'],
        ],
        'church_leader' => [
            'default' => 'church_leader',
            'options' => ['church_leader', 'pastor', 'local_pastor', 'priest', 'minister'],
        ],
        'superadmin' => [
            'default' => 'senior_leader',
            'options' => ['senior_leader', 'senior_pastor', 'regional_leader', 'bishop', 'apostle'],
        ],
        'system' => [
            'default' => 'system',
            'options' => ['system'],
        ],
    ],
];
