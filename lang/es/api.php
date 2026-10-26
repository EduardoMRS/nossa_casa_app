<?php

return [
    'push' => [
        'gateway_unauthorized' => 'La firma de la pasarela de notificaciones push no es válida.',
        'gateway_replayed' => 'La solicitud de la pasarela de notificaciones push ya fue procesada.',
        'invalid_deep_link' => 'Los enlaces push deben ser relativos o usar el esquema de Nossa Casa.',
        'payload_too_large' => 'El contenido push supera el límite de 2 KB de la aplicación.',
    ],
    'errors' => [
        'validation' => 'Los datos proporcionados no son válidos.',
        'unauthenticated' => 'Se requiere autenticación.',
        'forbidden' => 'No está autorizado para realizar esta acción.',
        'not_found' => 'No se encontró el recurso solicitado.',
        'https_required' => 'Se requiere una conexión segura HTTPS.',
        'invalid_instance_id' => 'NATIVE_INSTANCE_ID debe ser un ULID válido.',
    ],
];
