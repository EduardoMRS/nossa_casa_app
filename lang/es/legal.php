<?php

return [
    'kicker' => 'Información legal',
    'title' => 'Política de privacidad y términos de uso',
    'meta_description' => 'Política de privacidad y términos de uso de la plataforma Nossa Casa.',
    'back_to_portal' => 'Volver al portal',
    'updated_at' => 'Última actualización: 22 de agosto de 2026',
    'introduction' => 'Nossa Casa es un proyecto comunitario creado para ayudar a las iglesias a comunicarse, organizar eventos y servir a sus miembros. Esta página explica cómo se puede usar la plataforma y cómo se maneja la información.',
    'sections' => [
        [
            'title' => '1. Aceptación y propósito',
            'paragraphs' => [
                'Al acceder a Nossa Casa, aceptas usar la plataforma de forma legal, respetuosa y coherente con la misión de la iglesia o comunidad que opera el espacio.',
                'La plataforma puede ofrecer páginas públicas, áreas privadas, eventos, formularios, contenido bíblico, registro de entrada y salida de niños, comentarios, medios y transmisiones en vivo.',
            ],
            'items' => [],
        ],
        [
            'title' => '2. Información que manejamos',
            'paragraphs' => [
                'Según las funciones habilitadas por una iglesia, la plataforma puede manejar datos de la cuenta, información de contacto, membresía de la iglesia, inscripciones a eventos, respuestas de formularios, comentarios, registros de entrada y salida, medios y registros técnicos.',
                'Las iglesias son responsables de decidir qué información recopilan y de usarla únicamente con fines ministeriales, administrativos y de seguridad legítimos.',
            ],
            'items' => [
                'Solo recopilamos la información necesaria para la función que se está usando o para la seguridad y el funcionamiento del servicio.',
                'No se debe enviar información sensible en comentarios públicos, formularios públicos o chat en vivo.',
                'Los datos del registro de entrada de niños deben ser manejados por trabajadores autorizados de la iglesia y usarse solo para el cuidado, la identificación y la entrega segura.',
            ],
        ],
        [
            'title' => '3. Almacenamiento, seguridad y acceso',
            'paragraphs' => [
                'Los datos son almacenados por el operador de la aplicación o por la iglesia que aloja su propia instalación. Los medios pueden almacenarse en almacenamiento compatible con S3, como MinIO, y entregarse mediante URL temporales o firmadas.',
                'Aplicamos controles de acceso, autenticación, secretos cifrados y salvaguardas operativas adecuadas a la instalación. Ningún servicio de internet puede garantizar una seguridad absoluta, por lo que las credenciales nunca deben compartirse ni incluirse en el repositorio.',
            ],
            'items' => [
                'Los administradores de la iglesia pueden gestionar sus propios registros dentro de su ámbito de autorización.',
                'Los operadores del sistema pueden acceder a los datos de infraestructura cuando sea necesario para proporcionar, asegurar, mantener o recuperar el servicio.',
                'Los datos pueden conservarse mientras sean necesarios para la iglesia, las obligaciones legales, las copias de seguridad o las investigaciones de seguridad.',
            ],
        ],
        [
            'title' => '4. Transmisiones en vivo, comentarios y medios',
            'paragraphs' => [
                'Las transmisiones en vivo, los comentarios y los medios marcados como públicos pueden ser vistos, copiados o redistribuidos por otras personas. No publiques información personal, confidencial o sensible en áreas públicas.',
                'Una iglesia o un moderador autorizado puede eliminar comentarios, medios o transmisiones que infrinjan sus reglas, estos términos o la ley aplicable.',
            ],
            'items' => [],
        ],
        [
            'title' => '5. Biblia y contenido de terceros',
            'paragraphs' => [
                'Las versiones de la Biblia y otros materiales de la biblioteca siguen sujetos a los derechos y licencias de sus respectivos autores y proveedores. Úsalos solo dentro de los permisos mostrados por la aplicación o la licencia de origen.',
                'El proyecto no reclama la propiedad del contenido de terceros por el simple hecho de ponerlo a disposición a través de una función.',
            ],
            'items' => [],
        ],
        [
            'title' => '6. Disponibilidad y cambios',
            'paragraphs' => [
                'Nossa Casa se desarrolla como un proyecto comunitario y puede cambiar, interrumpirse por mantenimiento o tener funciones habilitadas o deshabilitadas por cada instalación. No se garantiza ningún período de disponibilidad o de conservación de datos, salvo que un acuerdo aparte indique lo contrario.',
                'Esta política y estos términos pueden actualizarse cuando cambien el proyecto, las leyes o las prácticas operativas. La página localizada se genera a partir del contenido de origen actual y puede ser traducida por el flujo de traducción del proyecto.',
            ],
            'items' => [],
        ],
        [
            'title' => '7. Contacto y solicitudes',
            'paragraphs' => [
                'Para preguntas, solicitudes de corrección, solicitudes de eliminación o inquietudes sobre la instalación de una iglesia, contacta a la iglesia responsable de esa instalación. Para el proyecto público, usa el gestor de incidencias del repositorio o el canal de contacto publicado por los mantenedores del proyecto.',
            ],
            'items' => [],
        ],
    ],
];
