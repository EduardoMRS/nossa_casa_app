<?php

namespace App\Services;

use App\Models\ServerProfile;

final class ChurchSelectionService
{
    /** @param array<string, mixed> $user */
    public function choose(ServerProfile $server, array $user): ?string
    {
        $memberships = collect($user['memberships'] ?? [])->filter(fn (mixed $membership): bool => is_array($membership));
        $selected = is_string($user['selected_church_id'] ?? null) ? $user['selected_church_id'] : null;

        if ($selected !== null && ! $memberships->contains('church_id', $selected)) {
            $selected = null;
        }

        if ($selected === null && $memberships->count() === 1) {
            $selected = $memberships->first()['church_id'] ?? null;
        }

        $server->forceFill(['selected_church_id' => $selected])->save();

        return $selected;
    }

    public function select(ServerProfile $server, string $churchId): void
    {
        $server->forceFill(['selected_church_id' => $churchId])->save();
    }
}
