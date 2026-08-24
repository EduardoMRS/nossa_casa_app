<?php

namespace App\Policies;

use App\Models\MobileSession;
use App\Models\User;

final class MobileSessionPolicy
{
    public function delete(User $user, MobileSession $mobileSession): bool
    {
        return $mobileSession->user_id === $user->id;
    }
}
