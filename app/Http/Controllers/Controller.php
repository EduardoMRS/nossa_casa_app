<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function ensureChurchAccess(Request $request, string $churchId): void
    {
        $role = $request->user()->role;

        if (in_array($role, [UserRole::ADMIN, UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        abort_unless($request->user()->church?->id === $churchId, 403);
    }

    protected function ensureOwnerOrModerator(Request $request, string $ownerId): void
    {
        $role = $request->user()->role;

        if (in_array($role, [UserRole::LEADER, UserRole::MEDIA, UserRole::ADMIN, UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        abort_unless($request->user()->id === $ownerId, 403);
    }
}
