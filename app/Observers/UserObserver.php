<?php

namespace App\Observers;

use App\Models\User;
use App\Services\MobileSessionService;

final class UserObserver
{
    public function __construct(private MobileSessionService $mobileSessions) {}

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if (
            $user->wasChanged('password')
            || ($user->wasChanged('blocked_at') && $user->blocked_at !== null)
        ) {
            $this->mobileSessions->revokeAllForUser($user, 'security_revocation');
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleting(User $user): void
    {
        $this->mobileSessions->revokeAllForUser($user, 'user_deleted');
    }
}
