<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return $group->is_public || $this->canManage($user, $group);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::LEADER, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM,
        ], true);
    }

    public function update(User $user, Group $group): bool
    {
        return $this->canManage($user, $group);
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->canManage($user, $group);
    }

    private function canManage(User $user, Group $group): bool
    {
        if ($user->role === UserRole::SYSTEM) {
            return true;
        }

        return $user->church?->id === $group->church_id
            && in_array($user->role, [
                UserRole::LEADER, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN,
            ], true);
    }
}
