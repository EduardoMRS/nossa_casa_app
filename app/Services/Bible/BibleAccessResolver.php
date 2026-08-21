<?php

namespace App\Services\Bible;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;

class BibleAccessResolver
{
    /** @return array{versions: list<string>, default_version: string|null, community_versions: list<string>, community_default_version: string|null} */
    public function forChurch(Church $church): array
    {
        $church->loadMissing(['community', 'settings']);
        $configuredCommunityVersions = $this->normalizeVersions($church->community?->bible_versions);
        $communityVersions = $configuredCommunityVersions !== []
            ? $configuredCommunityVersions
            : $this->normalizeVersions(config('bible.default_versions', []));
        $communityDefault = in_array($church->community?->default_bible_version, $communityVersions, true)
            ? $church->community->default_bible_version
            : ($communityVersions[0] ?? null);
        $churchVersions = $this->normalizeVersions(data_get($church->settings?->options, 'bible.versions', []));
        $allowedChurchVersions = array_values(array_intersect($churchVersions, $communityVersions));
        $versions = $allowedChurchVersions !== [] ? $allowedChurchVersions : $communityVersions;
        $configuredDefault = data_get($church->settings?->options, 'bible.default_version');
        $default = is_string($configuredDefault) && in_array($configuredDefault, $versions, true)
            ? $configuredDefault
            : (in_array($communityDefault, $versions, true) ? $communityDefault : ($versions[0] ?? null));

        return [
            'versions' => $versions,
            'default_version' => $default,
            'community_versions' => $communityVersions,
            'community_default_version' => $communityDefault,
        ];
    }

    public function canManageCommunity(User $user, Church $church): bool
    {
        return in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)
            || $church->community?->owner_id === $user->id;
    }

    /** @return list<string> */
    private function normalizeVersions(mixed $versions): array
    {
        if (! is_array($versions)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $versions,
            fn (mixed $version): bool => is_string($version) && $version !== '',
        )));
    }
}
