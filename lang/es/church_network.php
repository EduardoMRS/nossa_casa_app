<?php

return [
    'validation' => [
        'participant_required' => 'Tu iglesia debe ser una de las iglesias de esta solicitud.',
        'response_not_allowed' => 'Solo la iglesia invitada puede responder a esta solicitud.',
        'cycle' => 'Este cambio crearía un ciclo en la jerarquía de iglesias.',
        'same_church' => 'Una iglesia no puede ser su propia sede.',
        'same_community' => 'Las sedes y las filiales deben pertenecer a la misma comunidad.',
        'pending_required' => 'Esta solicitud ya no está pendiente.',
        'descendant_required' => 'Solo puedes reorganizar iglesias por debajo de tu iglesia en la jerarquía.',
    ],
];
