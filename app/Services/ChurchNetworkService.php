<?php

namespace App\Services;

use App\Enums\ChurchNetworkRequestStatus;
use App\Models\Church;
use App\Models\ChurchNetworkRequest;
use App\Models\Network;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChurchNetworkService
{
    public function request(Church $actorChurch, Church $parentChurch, Church $childChurch, User $user): ChurchNetworkRequest
    {
        $this->ensureValidPair($parentChurch, $childChurch);

        if (! in_array($actorChurch->id, [$parentChurch->id, $childChurch->id], true)) {
            throw ValidationException::withMessages([
                'church' => __('church_network.validation.participant_required'),
            ]);
        }

        if ($this->wouldCreateCycle($parentChurch, $childChurch)) {
            throw ValidationException::withMessages([
                'parent_church_id' => __('church_network.validation.cycle'),
            ]);
        }

        return DB::transaction(function () use ($actorChurch, $parentChurch, $childChurch, $user): ChurchNetworkRequest {
            ChurchNetworkRequest::query()
                ->where('child_church_id', $childChurch->id)
                ->where('status', ChurchNetworkRequestStatus::PENDING)
                ->update([
                    'status' => ChurchNetworkRequestStatus::CANCELLED,
                    'responded_at' => now(),
                ]);

            return ChurchNetworkRequest::query()->create([
                'requesting_church_id' => $actorChurch->id,
                'parent_church_id' => $parentChurch->id,
                'child_church_id' => $childChurch->id,
                'requested_by_id' => $user->id,
            ]);
        });
    }

    public function accept(Church $actorChurch, ChurchNetworkRequest $request, User $user): Network
    {
        $this->ensurePending($request);
        $respondingChurchId = $request->requesting_church_id === $request->parent_church_id
            ? $request->child_church_id
            : $request->parent_church_id;

        if ($actorChurch->id !== $respondingChurchId) {
            throw ValidationException::withMessages([
                'church' => __('church_network.validation.response_not_allowed'),
            ]);
        }

        $parentChurch = $request->parentChurch;
        $childChurch = $request->childChurch;
        $this->ensureValidPair($parentChurch, $childChurch);

        if ($this->wouldCreateCycle($parentChurch, $childChurch)) {
            throw ValidationException::withMessages([
                'parent_church_id' => __('church_network.validation.cycle'),
            ]);
        }

        return DB::transaction(function () use ($request, $parentChurch, $childChurch, $user): Network {
            Network::query()->where('child_church_id', $childChurch->id)->delete();

            $network = Network::query()->create([
                'parent_church_id' => $parentChurch->id,
                'child_church_id' => $childChurch->id,
                'community_id' => $parentChurch->community_id,
            ]);

            $request->update([
                'status' => ChurchNetworkRequestStatus::ACCEPTED,
                'responded_by_id' => $user->id,
                'responded_at' => now(),
            ]);

            ChurchNetworkRequest::query()
                ->whereKeyNot($request->id)
                ->where('child_church_id', $childChurch->id)
                ->where('status', ChurchNetworkRequestStatus::PENDING)
                ->update([
                    'status' => ChurchNetworkRequestStatus::CANCELLED,
                    'responded_at' => now(),
                ]);

            return $network;
        });
    }

    public function reject(Church $actorChurch, ChurchNetworkRequest $request, User $user): void
    {
        $this->ensurePending($request);
        $respondingChurchId = $request->requesting_church_id === $request->parent_church_id
            ? $request->child_church_id
            : $request->parent_church_id;

        if ($actorChurch->id !== $respondingChurchId) {
            throw ValidationException::withMessages([
                'church' => __('church_network.validation.response_not_allowed'),
            ]);
        }

        $request->update([
            'status' => ChurchNetworkRequestStatus::REJECTED,
            'responded_by_id' => $user->id,
            'responded_at' => now(),
        ]);
    }

    public function moveDescendant(Church $actorChurch, Church $childChurch, Church $newParentChurch): Network
    {
        $this->ensureValidPair($newParentChurch, $childChurch);
        $descendantIds = $this->descendantIds($actorChurch);

        if (! in_array($childChurch->id, $descendantIds, true)
            || ($newParentChurch->id !== $actorChurch->id && ! in_array($newParentChurch->id, $descendantIds, true))) {
            throw ValidationException::withMessages([
                'church' => __('church_network.validation.descendant_required'),
            ]);
        }

        if ($this->wouldCreateCycle($newParentChurch, $childChurch)) {
            throw ValidationException::withMessages([
                'parent_church_id' => __('church_network.validation.cycle'),
            ]);
        }

        return DB::transaction(function () use ($childChurch, $newParentChurch): Network {
            Network::query()->where('child_church_id', $childChurch->id)->delete();

            return Network::query()->create([
                'parent_church_id' => $newParentChurch->id,
                'child_church_id' => $childChurch->id,
                'community_id' => $newParentChurch->community_id,
            ]);
        });
    }

    /** @return list<string> */
    public function descendantIds(Church $church): array
    {
        $descendants = [];
        $pendingParentIds = [$church->id];

        while ($pendingParentIds !== []) {
            $children = Network::query()
                ->whereIn('parent_church_id', $pendingParentIds)
                ->pluck('child_church_id')
                ->filter(fn (string $id): bool => ! in_array($id, $descendants, true))
                ->values()
                ->all();
            $descendants = [...$descendants, ...$children];
            $pendingParentIds = $children;
        }

        return array_values(array_unique($descendants));
    }

    /** @return list<string> */
    public function relatedIds(Church $church): array
    {
        $relatedIds = $this->descendantIds($church);
        $currentId = $church->id;

        while ($currentId !== null) {
            $parentId = Network::query()->where('child_church_id', $currentId)->value('parent_church_id');

            if (! is_string($parentId) || in_array($parentId, $relatedIds, true)) {
                break;
            }

            $relatedIds[] = $parentId;
            $currentId = $parentId;
        }

        return array_values(array_unique($relatedIds));
    }

    private function wouldCreateCycle(Church $parentChurch, Church $childChurch): bool
    {
        return $parentChurch->id === $childChurch->id
            || in_array($parentChurch->id, $this->descendantIds($childChurch), true);
    }

    private function ensureValidPair(Church $parentChurch, Church $childChurch): void
    {
        if ($parentChurch->id === $childChurch->id) {
            throw ValidationException::withMessages([
                'parent_church_id' => __('church_network.validation.same_church'),
            ]);
        }

        if ($parentChurch->community_id === null || $parentChurch->community_id !== $childChurch->community_id) {
            throw ValidationException::withMessages([
                'parent_church_id' => __('church_network.validation.same_community'),
            ]);
        }
    }

    private function ensurePending(ChurchNetworkRequest $request): void
    {
        if ($request->status !== ChurchNetworkRequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'request' => __('church_network.validation.pending_required'),
            ]);
        }
    }
}
