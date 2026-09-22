<?php

namespace App\Http\Controllers;

use App\Actions\Communities\BuildCommunityTree;
use App\Enums\ChurchStatus;
use App\Enums\LiveStreamStatus;
use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use App\Support\ChurchDomainContext;
use App\Support\ChurchTerminology;
use App\Support\GeoDistance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalCommunityController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly BuildCommunityTree $buildCommunityTree,
        private readonly ChurchTerminology $terminology,
        private readonly GeoDistance $geoDistance,
    ) {}

    public function __invoke(Request $request, string $locale, Community $community): Response|RedirectResponse
    {
        if (! $this->context->isMainDomain()) {
            return redirect()->away($this->communityUrl($community, $locale));
        }

        $location = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
        $latitude = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $longitude = isset($location['longitude']) ? (float) $location['longitude'] : null;

        $community->load([
            'churches' => fn ($query) => $query
                ->where('status', ChurchStatus::ACTIVE)
                ->with(['address', 'settings'])
                ->withCount([
                    'members',
                    'events as upcoming_events_count' => fn ($events) => $events->where('start_time', '>=', now()),
                ])
                ->withExists(['liveStreams as is_live' => fn ($liveStreams) => $liveStreams
                    ->publiclyVisible()
                    ->where('status', LiveStreamStatus::LIVE)
                    ->where('active_slot', 1)])
                ->orderBy('name'),
        ]);
        $community->localize(relations: ['churches']);

        $relationships = Network::query()
            ->where('community_id', $community->id)
            ->whereIn('parent_church_id', $community->churches->pluck('id'))
            ->whereIn('child_church_id', $community->churches->pluck('id'))
            ->oldest()
            ->get(['parent_church_id', 'child_church_id'])
            ->map(fn (Network $network): array => [
                'parent_church_id' => $network->parent_church_id,
                'child_church_id' => $network->child_church_id,
            ]);
        $childChurchIds = $relationships->pluck('child_church_id');
        $churches = $community->churches
            ->map(fn ($church): array => $this->churchData(
                $church,
                $childChurchIds->contains($church->id),
                $latitude,
                $longitude,
            ))
            ->sort(fn (array $first, array $second): int => $this->compareByDistanceAndName($first, $second))
            ->values();

        return Inertia::render('Portal/CommunityShow', [
            'community' => [
                'id' => $community->id,
                'name' => $community->name,
                'slug' => $community->slug,
                'description' => $community->description,
                'found_date' => $community->found_date,
                'logo_url' => $community->logo_path ? genUrl($community->logo_path) : null,
            ],
            'stats' => [
                'churches' => $churches->count(),
                'members' => $churches->sum('members_count'),
                'upcoming_events' => $churches->sum('upcoming_events_count'),
            ],
            'churches' => $churches,
            'tree' => $this->buildCommunityTree->handle($churches, $relationships),
            'locationApplied' => $latitude !== null && $longitude !== null,
            'userChurchUrl' => $request->user()?->church?->domain
                ? $this->context->churchUrl($request->user()->church)
                : null,
            'userChurchId' => $request->user()?->church?->id,
        ]);
    }

    private function communityUrl(Community $community, string $locale): string
    {
        return rtrim((string) config('app.url'), '/').route('communities.show', [
            'locale' => $locale,
            'community' => $community,
        ], absolute: false);
    }

    /** @return array<string, mixed> */
    private function churchData(
        Church $church,
        bool $isBranch,
        ?float $latitude,
        ?float $longitude,
    ): array {
        $address = $church->address->first();
        $branding = is_array($church->settings?->options['branding'] ?? null)
            ? $church->settings->options['branding']
            : [];
        $savedTerminology = is_array($church->settings?->options['terminology'] ?? null)
            ? $church->settings->options['terminology']
            : [];
        $resolvedTerminology = $this->terminology->resolvedForChurch($church);
        $churchLatitude = $address?->latitude ?? data_get($branding, 'latitude');
        $churchLongitude = $address?->longitude ?? data_get($branding, 'longitude');
        $distance = $latitude !== null && $longitude !== null && is_numeric($churchLatitude) && is_numeric($churchLongitude)
            ? $this->geoDistance->between($latitude, $longitude, (float) $churchLatitude, (float) $churchLongitude)
            : null;
        $unitScope = $isBranch ? 'branch' : 'headquarters';

        return [
            'id' => $church->id,
            'name' => $church->name,
            'slug' => $church->slug,
            'domain' => $church->domain,
            'url' => $church->domain ? $this->context->churchUrl($church) : null,
            'unit_label' => $resolvedTerminology['units'][$unitScope]['singular'],
            'is_live' => (bool) $church->is_live,
            'members_count' => (int) $church->members_count,
            'upcoming_events_count' => (int) $church->upcoming_events_count,
            'distance_km' => $distance,
            'city' => $address?->city,
            'state' => $address?->state,
            'address' => collect([
                $address?->street,
                $address?->number,
                $address?->neighborhood,
                $address?->city,
                $address?->state,
            ])->filter()->join(', '),
        ];
    }

    /**
     * @param  array{name: string, distance_km: float|null}  $first
     * @param  array{name: string, distance_km: float|null}  $second
     */
    private function compareByDistanceAndName(array $first, array $second): int
    {
        if (($first['distance_km'] === null) !== ($second['distance_km'] === null)) {
            return $first['distance_km'] === null ? 1 : -1;
        }

        if ($first['distance_km'] !== null && $first['distance_km'] !== $second['distance_km']) {
            return $first['distance_km'] <=> $second['distance_km'];
        }

        return strnatcasecmp($first['name'], $second['name']);
    }
}
