<?php

return [
    'validation' => [
        'participant_required' => 'Sua igreja deve ser uma das participantes desta solicitação.',
        'response_not_allowed' => 'Somente a igreja convidada pode responder a esta solicitação.',
        'cycle' => 'Esta alteração criaria um ciclo na hierarquia de igrejas.',
        'same_church' => 'Uma igreja não pode ser sua própria matriz.',
        'same_community' => 'Matrizes e filiais devem pertencer à mesma comunidade.',
        'pending_required' => 'Esta solicitação não está mais pendente.',
        'descendant_required' => 'Você só pode reorganizar igrejas inferiores à sua na hierarquia.',
    ],
];
