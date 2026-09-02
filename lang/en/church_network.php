<?php

return [
    'validation' => [
        'participant_required' => 'Your church must be one of the churches in this request.',
        'response_not_allowed' => 'Only the invited church may respond to this request.',
        'cycle' => 'This change would create a cycle in the church hierarchy.',
        'same_church' => 'A church cannot be its own headquarters.',
        'same_community' => 'Headquarters and branches must belong to the same community.',
        'pending_required' => 'This request is no longer pending.',
        'descendant_required' => 'You may only reorganize churches below your church in the hierarchy.',
    ],
];
