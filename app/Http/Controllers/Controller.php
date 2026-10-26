<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\ChurchAccess;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Roles that moderate content written by other users of a church.
     *
     * @var array<int, UserRole>
     */
    private const MODERATOR_ROLES = [
        UserRole::LEADER,
        UserRole::MEDIA,
        UserRole::CHURCH_LEADER,
    ];

    protected function churchAccess(): ChurchAccess
    {
        return app(ChurchAccess::class);
    }

    /**
     * The church must be the user's own church or one of its descendants.
     * SYSTEM bypasses; nobody else (including SUPERADMIN) gets a global pass.
     */
    protected function ensureChurchAccess(Request $request, ?string $churchId): void
    {
        abort_unless($this->churchAccess()->canReachChurch($request->user(), $churchId), 403);
    }

    /**
     * Resolve the church a write operation targets (current context or the
     * user's own church) and make sure the user may manage it. Prevents
     * creating resources in another church through a forged X-Church-ID,
     * ?church_id or PWA cookie.
     *
     * @param  array<int, UserRole>|null  $roles
     */
    protected function managedChurchId(Request $request, ?array $roles = null, ?string $missingMessage = null): string
    {
        $churchId = app(ChurchDomainContext::class)->churchId()
            ?? ChurchAccess::homeChurchId($request->user());

        abort_unless(is_string($churchId) && $churchId !== '', 422, $missingMessage ?? __('church.context.required'));
        abort_unless($this->churchAccess()->canManageChurch($request->user(), $churchId, $roles), 403);

        return $churchId;
    }

    protected function ensurePublicChurchResource(string $churchId): void
    {
        abort_unless($this->churchAccess()->canReachChurch(request()->user(), $churchId), 403);
    }

    protected function ensureOwnerOrChurchModerator(Request $request, string $ownerId, ?string $churchId): void
    {
        $user = $request->user();

        if ($user?->id === $ownerId || ChurchAccess::isSystem($user)) {
            return;
        }

        abort_unless($this->churchAccess()->canManageChurch($user, $churchId, self::MODERATOR_ROLES), 403);
    }

    /**
     * Owner or a moderator role. Always pair with ensureChurchAccess, since
     * this check is role-only.
     */
    protected function ensureOwnerOrModerator(Request $request, string $ownerId): void
    {
        $user = $request->user();

        if ($user?->id === $ownerId || ChurchAccess::isSystem($user)) {
            return;
        }

        abort_unless(in_array($user?->role, self::MODERATOR_ROLES, true), 403);
    }
}
