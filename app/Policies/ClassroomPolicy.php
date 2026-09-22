<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $classroom->canBeManagedBy($user)
            || ($classroom->portal_enabled && $classroom->hasMember($user));
    }

    public function manage(User $user, Classroom $classroom): bool
    {
        return $classroom->canBeManagedBy($user);
    }

    public function submitActivity(User $user, Classroom $classroom): bool
    {
        return $classroom->portal_enabled && $classroom->hasMember($user);
    }

    public function participateInForum(User $user, Classroom $classroom): bool
    {
        return $classroom->portal_enabled
            && $classroom->hasMember($user)
            && ($classroom->portal_settings['forum_enabled'] ?? true);
    }
}
