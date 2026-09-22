<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\ChurchNetworkService;
use App\Support\ChurchDomainContext;
use App\Support\GeoDistance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChurchProximityController extends Controller
{
    private const DISTANCE_THRESHOLD_KM = 30;

    public function __construct(
        private readonly ChurchNetworkService $networks,
        private readonly ChurchDomainContext $context,
        private readonly GeoDistance $distance,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $church = $request->user()?->church;

        if (! $church instanceof Church) {
            return response()->json(['should_prompt' => false, 'alternatives' => []]);
        }

        $church->loadMissing(['address', 'settings']);
        $ownDistance = $this->distanceFrom(
            $church,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        if ($ownDistance === null || $ownDistance <= self::DISTANCE_THRESHOLD_KM) {
            return response()->json([
                'should_prompt' => false,
                'distance_km' => $ownDistance,
                'alternatives' => [],
            ]);
        }

        $relatedIds = $this->networks->relatedIds($church);
        $alternatives = $this->nearbyChurches(
            $church,
            $relatedIds,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );
        $source = 'network';

        if ($alternatives === []) {
            $alternatives = $this->nearbyChurches(
                $church,
                null,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            );
            $source = 'community';
        }

        if ($alternatives === []) {
            $alternatives = $this->nearbyChurches(
                $church,
                $relatedIds,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                withinThreshold: false,
            );
            $source = 'network';

            if ($alternatives === []) {
                $alternatives = $this->nearbyChurches(
                    $church,
                    null,
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    withinThreshold: false,
                );
                $source = 'community';
            }
        }

        return response()->json([
            'should_prompt' => true,
            'distance_km' => $ownDistance,
            'own_church' => [
                'id' => $church->id,
                'name' => $church->name,
                'url' => $this->context->churchUrl($church),
            ],
            'source' => $source,
            'alternatives' => $alternatives,
        ]);
    }

    /**
     * @param  list<string>|null  $churchIds
     * @return list<array{id: string, name: string, url: string, distance_km: float}>
     */
    private function nearbyChurches(
        Church $church,
        ?array $churchIds,
        float $latitude,
        float $longitude,
        bool $withinThreshold = true,
    ): array {
        if (is_array($churchIds) && $churchIds === []) {
            return [];
        }

        return Church::query()
            ->where('community_id', $church->community_id)
            ->whereKeyNot($church->id)
            ->when(is_array($churchIds), fn ($query) => $query->whereKey($churchIds))
            ->with(['address', 'settings'])
            ->get(['id', 'name', 'domain', 'community_id'])
            ->map(function (Church $candidate) use ($latitude, $longitude, $withinThreshold): ?array {
                $distance = $this->distanceFrom($candidate, $latitude, $longitude);

                if ($distance === null || ($withinThreshold && $distance > self::DISTANCE_THRESHOLD_KM)) {
                    return null;
                }

                return [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'url' => $this->context->churchUrl($candidate),
                    'distance_km' => $distance,
                ];
            })
            ->filter()
            ->sortBy('distance_km')
            ->take(6)
            ->values()
            ->all();
    }

    private function distanceFrom(Church $church, float $latitude, float $longitude): ?float
    {
        $address = $church->address->first();
        $branding = $church->settings?->options['branding'] ?? [];
        $churchLatitude = $address?->latitude ?? data_get($branding, 'latitude');
        $churchLongitude = $address?->longitude ?? data_get($branding, 'longitude');

        if (! is_numeric($churchLatitude) || ! is_numeric($churchLongitude)) {
            return null;
        }

        return $this->distance->between(
            $latitude,
            $longitude,
            (float) $churchLatitude,
            (float) $churchLongitude,
        );
    }
}
