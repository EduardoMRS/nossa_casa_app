<?php

return [
    'push' => [
        'gateway_unauthorized' => 'A assinatura do gateway de push é inválida.',
        'gateway_replayed' => 'A solicitação do gateway de push já foi processada.',
        'invalid_deep_link' => 'Os links de push devem ser relativos ou usar o esquema do Nossa Casa.',
        'payload_too_large' => 'O conteúdo do push excede o limite de 2 KB da aplicação.',
    ],
    'errors' => [
        'validation' => 'Os dados informados são inválidos.',
        'unauthenticated' => 'É necessário estar autenticado.',
        'forbidden' => 'Você não tem autorização para realizar esta ação.',
        'not_found' => 'O recurso solicitado não foi encontrado.',
        'https_required' => 'É necessária uma conexão HTTPS segura.',
        'invalid_instance_id' => 'NATIVE_INSTANCE_ID deve ser um ULID válido.',
    ],
];
