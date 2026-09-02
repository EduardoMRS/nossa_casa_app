<?php

return [
    'push' => [
        'gateway_unauthorized' => 'The push gateway signature is invalid.',
        'gateway_replayed' => 'The push gateway request was already processed.',
        'invalid_deep_link' => 'Push links must be relative or use the Nossa Casa scheme.',
        'payload_too_large' => 'The push payload exceeds the 2 KB application limit.',
    ],
    'errors' => [
        'validation' => 'The given data was invalid.',
        'unauthenticated' => 'Authentication is required.',
        'forbidden' => 'You are not authorized to perform this action.',
        'not_found' => 'The requested resource was not found.',
        'https_required' => 'A secure HTTPS connection is required.',
        'invalid_instance_id' => 'NATIVE_INSTANCE_ID must be a valid ULID.',
    ],
];
