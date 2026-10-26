<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use App\Services\ChurchNetworkService;

/**
 * Single source of truth for church-scoped authorization.
 *
 * Rules:
 * - SYSTEM bypasses every church check, except billing (see canManageTreasury).
 * - Every other user acts only inside their own church and the churches below it
 *   in the network hierarchy (branches and branches of branches). A branch can
 *   never reach its headquarters, sibling or unrelated churches.
 * - SUPERADMIN is community leadership: it never manages or moderates church
 *   resources (only community tools such as church registration review).
 */
final class ChurchAccess
{
    /** Roles that may change church content/configuration (their scope only). */
    public const MANAGER_ROLES = [
        UserRole::LEADER,
        UserRole::MEDIA,
        UserRole::CHURCH_LEADER,
    ];

    /** Roles that may handle billing/treasury of their own church. */
    public const TREASURY_ROLES = [
        UserRole::TREASURER,
        UserRole::CHURCH_LEADER,
    ];

    /** @var array<string, list<string>> */
    private array $scopeCache = [];

    public function __construct(private ChurchNetworkService $network) {}

    public static function isSystem(?User $user): bool
    {
        return $user?->role === UserRole::SYSTEM;
    }

    public static function homeChurchId(?User $user): ?string
    {
        $churchId = $user?->profile?->church_id;

        return is_string($churchId) && $churchId !== '' ? $churchId : null;
    }

    /**
     * Churches the user can reach: own church first, then every descendant.
     *
     * @return list<string>
     */
    public function scopeChurchIds(?User $user): array
    {
        $homeChurchId = self::homeChurchId($user);

        if ($homeChurchId === null) {
            return [];
        }

        $cacheKey = $user->id.':'.$homeChurchId;

        if (! array_key_exists($cacheKey, $this->scopeCache)) {
            $homeChurch = Church::query()->find($homeChurchId);

            $this->scopeCache[$cacheKey] = $homeChurch === null
                ? []
                : array_values(array_unique([$homeChurchId, ...$this->network->descendantIds($homeChurch)]));
        }

        return $this->scopeCache[$cacheKey];
    }

    /**
     * Whether the user may reach the church (own church or descendant).
     */
    public function canReachChurch(?User $user, ?string $churchId): bool
    {
        if ($user === null || ! is_string($churchId) || $churchId === '') {
            return false;
        }

        if (self::isSystem($user)) {
            return true;
        }

        return in_array($churchId, $this->scopeChurchIds($user), true);
    }

    /**
     * Whether the user may manage (create/update/delete) resources of the church.
     *
     * @param  array<int, UserRole>|null  $roles  Allowed roles; defaults to MANAGER_ROLES.
     */
    public function canManageChurch(?User $user, ?string $churchId, ?array $roles = null): bool
    {
        if (self::isSystem($user)) {
            return $user !== null && is_string($churchId) && $churchId !== '';
        }

        return $user !== null
            && in_array($user->role, $roles ?? self::MANAGER_ROLES, true)
            && $this->canReachChurch($user, $churchId);
    }

    /**
     * Community-level tools (church registration review, community Bible
     * versions) are reserved to SYSTEM, the community owner or the community
     * leadership (superadmin) bound to that community.
     */
    public function managesCommunity(?User $user, ?string $communityId): bool
    {
        if ($user === null || ! is_string($communityId) || $communityId === '') {
            return false;
        }

        if (self::isSystem($user)) {
            return true;
        }

        if (Community::query()->whereKey($communityId)->where('owner_id', $user->id)->exists()) {
            return true;
        }

        return $user->role === UserRole::SUPERADMIN
            && $user->profile?->community_id === $communityId;
    }

    /**
     * Billing is the only area SYSTEM cannot bypass. Only the treasurer or the
     * church leadership of the church itself may access it (not even the
     * headquarters of a branch, since each church receives its own funds).
     */
    public function canManageTreasury(?User $user, ?string $churchId): bool
    {
        return $user !== null
            && is_string($churchId)
            && $churchId !== ''
            && in_array($user->role, self::TREASURY_ROLES, true)
            && self::homeChurchId($user) === $churchId;
    }

    /**
     * Who may SEE treasury figures (read-only). The treasurer and the pastor of
     * the church itself can always see it; roles above the pastor (community
     * and system) may monitor the figures of a church in their reach, but they
     * can never change settings nor export (see canManageTreasury).
     */
    public function canViewTreasury(?User $user, ?string $churchId): bool
    {
        if ($this->canManageTreasury($user, $churchId)) {
            return true;
        }

        return $user !== null
            && in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)
            && (self::isSystem($user) || $this->managesCommunityChurch($user, $churchId));
    }

    /**
     * Whether the user's community contains the given church (for superadmin
     * read-only monitoring).
     */
    private function managesCommunityChurch(?User $user, ?string $churchId): bool
    {
        if (! is_string($churchId) || $churchId === '') {
            return false;
        }

        $communityId = Church::query()->whereKey($churchId)->value('community_id');

        return $this->managesCommunity($user, is_string($communityId) ? $communityId : null);
    }
}
