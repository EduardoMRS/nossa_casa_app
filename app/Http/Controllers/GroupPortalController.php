<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Support\ChurchDomainContext;
use App\Support\GeoDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GroupPortalController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $domainContext,
        private readonly GeoDistance $distance,
    ) {}

    public function index(Request $request): Response
    {
        $churchId = $this->domainContext->churchId();
        abort_if($churchId === null, 404);

        $location = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
        $latitude = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $longitude = isset($location['longitude']) ? (float) $location['longitude'] : null;

        $groups = Group::query()
            ->where('church_id', $churchId)
            ->where('is_public', true)
            ->with(['categories:id,name,slug,type', 'address', 'responsibleUsers:id,first_name,last_name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Group $group): Group => $group->localize(relations: ['categories']))
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'social_link' => $group->social_link,
                'categories' => $group->categories->pluck('name')->values()->all(),
                'responsibles' => $group->responsibleUsers->map(fn ($user): string => $user->name)->all(),
                'location' => $group->address?->to_string,
                'distance_km' => $this->distanceFor($group, $latitude, $longitude),
            ]);

        $nearest = $groups->whereNotNull('distance_km')->sortBy('distance_km')->first();

        return Inertia::render('Groups/Index', [
            'categories' => $this->groupByCategory($groups),
            'nearest' => $nearest,
            'hasLocation' => $latitude !== null && $longitude !== null,
        ]);
    }

    private function distanceFor(Group $group, ?float $latitude, ?float $longitude): ?float
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $address = $group->address;

        if ($address?->latitude === null || $address->longitude === null) {
            return null;
        }

        return $this->distance->between(
            $latitude,
            $longitude,
            (float) $address->latitude,
            (float) $address->longitude,
        );
    }

    /**
     * Group the public groups by their category label so the listing can show
     * one section per category (uncategorized groups fall into "Outros").
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return array<int, array{category: string, groups: array<int, array<string, mixed>>}>
     */
    private function groupByCategory($groups): array
    {
        $sections = [];

        foreach ($groups as $group) {
            $labels = $group['categories'] !== [] ? $group['categories'] : [__('group.uncategorized')];

            foreach ($labels as $label) {
                $sections[$label][] = $group;
            }
        }

        ksort($sections);

        return collect($sections)
            ->map(fn (array $items, string $category): array => ['category' => $category, 'groups' => $items])
            ->values()
            ->all();
    }
}
