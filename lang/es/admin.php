<?php

return [
    'categories' => [
        'description' => 'Cree y edite las categorías usadas por eventos, publicaciones, medios, formularios, la biblioteca y las clases.',
        'subtitle' => 'Administración y configuración',
        'title' => 'Categorías de la iglesia',
        'types' => [
            'classrooms' => 'Clases',
            'events' => 'Eventos',
            'forms' => 'Formularios',
            'library' => 'Biblioteca',
            'media' => 'Medios',
            'posts' => 'Publicaciones',
            'groups' => 'Grupos',
        ],
    ],
    'media' => [
        'actions' => ['Abrir galería pública', 'Ir al panel'],
        'description' => 'Haga seguimiento de la aprobación, edite medios y gestione las publicaciones de la comunidad.',
        'stats' => ['Medios enviados', 'Pendientes', 'Aprobados', 'Rechazados'],
        'subtitle' => 'Comunicación y contenido',
        'title' => 'Moderar galería',
    ],
    'modules' => [
        'library' => [
            'actions' => ['Abrir inicio público', 'Revisar destacados'],
            'description' => 'Gestione los materiales devocionales, la colección digital y los versículos destacados.',
            'stats' => ['Materiales de la biblioteca', 'Versículos registrados', 'Categorías de contenido'],
            'subtitle' => 'Comunicación y contenido',
            'title' => 'Biblioteca y versículo',
        ],
        'prayer_requests' => [
            'actions' => ['Abrir mis oraciones', 'Ir al panel'],
            'description' => 'Revise las solicitudes entrantes y organice el seguimiento pastoral.',
            'stats' => ['Solicitudes recibidas', 'Solicitudes anónimas', 'Solicitudes identificadas'],
            'subtitle' => 'Ministerios y miembros',
            'title' => 'Solicitudes de intercesión',
        ],
    ],
    'multicongregation' => [
        'stats' => ['Iglesias', 'Comunidades', 'Vínculos de sede/filial'],
    ],
    'prayers' => [
        'stats' => ['Solicitudes enviadas', 'Solicitudes de la iglesia'],
    ],
    'users' => [
        'stats' => ['Total de usuarios', 'Administradores', 'Miembros'],
    ],
    'event_registrations' => [
        'filename' => 'inscripciones',
        'export_generated_at' => 'Generado el :date',
        'individual_export' => 'Inscripción completa del participante',
        'pdf' => [
            'field' => 'Campo',
            'value' => 'Información',
            'participant' => 'Participante :number',
            'project_reference' => 'Nossa Casa - proyecto de código abierto',
        ],
        'errors' => [
            'create_export_file' => 'No se pudo crear el archivo de exportación.',
            'create_spreadsheet_archive' => 'No se pudo crear el archivo de la hoja de cálculo.',
            'read_spreadsheet_export' => 'No se pudo leer la exportación de la hoja de cálculo.',
        ],
        'statuses' => [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'canceled' => 'Cancelado',
            'confirmed' => 'Confirmado',
        ],
        'columns' => [
            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'status' => 'Estado',
            'registered_at' => 'Inscrito el',
        ],
    ],
    'wall' => [
        'stats' => ['Total de comentarios', 'Publicaciones con comentarios', 'Eventos con comentarios'],
    ],
];
