<?php

namespace App\Services;

use App\Enums\ChurchNetworkRequestStatus;
use App\Models\Church;
use App\Models\ChurchNetworkRequest;
use App\Models\Network;

final readonly class ChurchNetworkSettingsData
{
    public function __construct(private ChurchNetworkService $networks) {}

    /** @return array<string, mixed> */
    public function overviewForChurch(Church $church): array
    {
        $churchIds = [$church->id, ...$this->networks->descendantIds($church)];
        $parentNetwork = Network::query()
            ->where('child_church_id', $church->id)
            ->with('parentChurch:id,name')
            ->first();
        $networkRows = Network::query()
            ->whereIn('parent_church_id', $churchIds)
            ->whereIn('child_church_id', $churchIds)
            ->with(['parentChurch:id,name', 'childChurch:id,name'])
            ->orderBy('created_at')
            ->get();
        $ancestors = collect();
        $visitedChurchIds = [$church->id];
        $ancestorChurchId = $church->id;

        while (true) {
            $ancestorNetwork = Network::query()
                ->where('child_church_id', $ancestorChurchId)
                ->with('parentChurch:id,name')
                ->first();
            $ancestor = $ancestorNetwork?->parentChurch;

            if (! $ancestor || in_array($ancestor->id, $visitedChurchIds, true)) {
                break;
            }

            $ancestors->prepend($ancestor->only(['id', 'name']));
            $visitedChurchIds[] = $ancestor->id;
            $ancestorChurchId = $ancestor->id;
        }

        $childrenByParent = $networkRows->groupBy('parent_church_id');
        $buildTree = function (Church $node) use (&$buildTree, $childrenByParent): array {
            $children = $childrenByParent
                ->get($node->id, collect())
                ->pluck('childChurch')
                ->filter()
                ->map(fn (Church $child): array => $buildTree($child))
                ->values()
                ->all();

            return [
                'id' => $node->id,
                'name' => $node->name,
                'children' => $children,
            ];
        };
        $tree = $buildTree($church);
        $treeDepth = function (array $node) use (&$treeDepth): int {
            if ($node['children'] === []) {
                return 0;
            }

            return 1 + max(array_map($treeDepth, $node['children']));
        };

        return [
            'church' => $church->only(['id', 'name', 'community_id']),
            'parent' => $parentNetwork?->parentChurch?->only(['id', 'name']),
            'children' => $networkRows
                ->where('parent_church_id', $church->id)
                ->pluck('childChurch')
                ->filter()
                ->map->only(['id', 'name'])
                ->values(),
            'ancestors' => $ancestors->values(),
            'tree' => $tree,
            'stats' => [
                'direct_branches' => $networkRows->where('parent_church_id', $church->id)->count(),
                'all_branches' => $networkRows->count(),
                'levels' => $treeDepth($tree),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function forChurch(Church $church): array
    {
        $overview = $this->overviewForChurch($church);
        $churchIds = [$church->id, ...$this->networks->descendantIds($church)];
        $networkRows = Network::query()
            ->whereIn('parent_church_id', $churchIds)
            ->whereIn('child_church_id', $churchIds)
            ->with(['parentChurch:id,name', 'childChurch:id,name'])
            ->orderBy('created_at')
            ->get();
        $pendingRequests = ChurchNetworkRequest::query()
            ->where('status', ChurchNetworkRequestStatus::PENDING)
            ->where(function ($query) use ($church): void {
                $query->where('requesting_church_id', $church->id)
                    ->orWhere(function ($incomingQuery) use ($church): void {
                        $incomingQuery
                            ->where('requesting_church_id', '!=', $church->id)
                            ->where(function ($participantQuery) use ($church): void {
                                $participantQuery->where('parent_church_id', $church->id)
                                    ->orWhere('child_church_id', $church->id);
                            });
                    });
            })
            ->with([
                'requestingChurch:id,name',
                'parentChurch:id,name',
                'childChurch:id,name',
            ])
            ->latest()
            ->get();

        return [
            ...$overview,
            'availableChurches' => Church::query()
                ->where('community_id', $church->community_id)
                ->whereKeyNot($church->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map->only(['id', 'name'])
                ->values(),
            'networks' => $networkRows->map(fn (Network $network): array => [
                'id' => $network->id,
                'parent' => $network->parentChurch?->only(['id', 'name']),
                'child' => $network->childChurch?->only(['id', 'name']),
            ])->values(),
            'requests' => $pendingRequests->map(fn (ChurchNetworkRequest $request): array => [
                'id' => $request->id,
                'direction' => $request->requesting_church_id === $church->id ? 'outgoing' : 'incoming',
                'requesting_church' => $request->requestingChurch?->only(['id', 'name']),
                'parent' => $request->parentChurch?->only(['id', 'name']),
                'child' => $request->childChurch?->only(['id', 'name']),
            ])->values(),
        ];
    }
}
