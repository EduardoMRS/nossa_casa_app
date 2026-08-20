<?php

namespace App\Actions\Communities;

use Illuminate\Support\Collection;

final class BuildCommunityTree
{
    /**
     * @param  Collection<int, array<string, mixed>>  $churches
     * @param  Collection<int, array{parent_church_id: string, child_church_id: string}>  $relationships
     * @return array<int, array<string, mixed>>
     */
    public function handle(Collection $churches, Collection $relationships): array
    {
        $nodes = $churches->keyBy('id');
        $childrenByParent = $relationships
            ->groupBy('parent_church_id')
            ->map(fn (Collection $items): array => $items->pluck('child_church_id')->all());
        $childIds = $relationships->pluck('child_church_id')->unique();
        $rootIds = $nodes->keys()->diff($childIds);

        if ($rootIds->isEmpty()) {
            $rootIds = $nodes->keys();
        }

        $buildNode = function (string $churchId, array $ancestors = []) use (&$buildNode, $childrenByParent, $nodes): ?array {
            if (in_array($churchId, $ancestors, true) || ! $nodes->has($churchId)) {
                return null;
            }

            $node = $nodes->get($churchId);
            $nextAncestors = [...$ancestors, $churchId];
            $children = collect($childrenByParent->get($churchId, []))
                ->map(fn (string $childId): ?array => $buildNode($childId, $nextAncestors))
                ->filter()
                ->values()
                ->all();

            return [...$node, 'children' => $children];
        };

        return $rootIds
            ->map(fn (string $churchId): ?array => $buildNode($churchId))
            ->filter()
            ->values()
            ->all();
    }
}
