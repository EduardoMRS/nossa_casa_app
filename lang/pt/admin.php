<?php

return [
    'categories' => [
        'description' => 'Crie e edite as categorias usadas por eventos, publicações, mídias, formulários, biblioteca e salas.',
        'subtitle' => 'Administração e configuração',
        'title' => 'Categorias por igreja',
        'types' => [
            'classrooms' => 'Salas',
            'events' => 'Eventos',
            'forms' => 'Formulários',
            'library' => 'Biblioteca',
            'media' => 'Mídias',
            'posts' => 'Publicações',
        ],
    ],
    'media' => [
        'actions' => ['Abrir galeria pública', 'Ir para o painel'],
        'description' => 'Acompanhe aprovações, edite mídias e gerencie publicações da comunidade.',
        'stats' => ['Mídias enviadas', 'Pendentes', 'Aprovadas', 'Rejeitadas'],
        'subtitle' => 'Comunicação e conteúdo',
        'title' => 'Moderar galeria',
    ],
    'modules' => [
        'library' => [
            'actions' => ['Abrir página pública', 'Revisar destaques'],
            'description' => 'Gerencie materiais devocionais, o acervo digital e os versículos em destaque.',
            'stats' => ['Materiais na biblioteca', 'Versículos cadastrados', 'Categorias de conteúdo'],
            'subtitle' => 'Comunicação e conteúdo',
            'title' => 'Biblioteca e versículo',
        ],
        'prayer_requests' => [
            'actions' => ['Abrir minhas orações', 'Ir para o painel'],
            'description' => 'Revise os pedidos recebidos e organize o acompanhamento pastoral.',
            'stats' => ['Pedidos recebidos', 'Pedidos anônimos', 'Pedidos identificados'],
            'subtitle' => 'Ministérios e membros',
            'title' => 'Pedidos de intercessão',
        ],
    ],
    'multicongregation' => [
        'stats' => ['Igrejas', 'Comunidades', 'Vínculos matriz/filial'],
    ],
    'prayers' => [
        'stats' => ['Pedidos enviados', 'Pedidos da igreja'],
    ],
    'users' => [
        'stats' => ['Usuários totais', 'Administradores', 'Membros'],
    ],
    'wall' => [
        'stats' => ['Comentários totais', 'Publicações com comentários', 'Eventos comentados'],
    ],
];
