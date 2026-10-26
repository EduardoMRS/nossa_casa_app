<?php

namespace App\Services;

use App\Models\PublicAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records anonymous accesses to public pages and aggregates them for the
 * access flow and visitor map. No user identifier is ever stored.
 */
class PublicAccessLogger
{
    public function __construct(private VisitorGeolocator $geolocator) {}

    /**
     * Persist an anonymous access log for the given request.
     */
    public function record(Request $request, ?string $churchId = null): ?PublicAccessLog
    {
        try {
            $ip = $request->ip();
            $location = $this->geolocator->locate($ip);

            return PublicAccessLog::create([
                'church_id' => $churchId,
                'ip_hash' => $this->hashIp($ip),
                'country_code' => $location['country_code'],
                'country' => $location['country'],
                'region' => $location['region'],
                'city' => $location['city'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 2048, ''),
                'route_name' => $request->route()?->getName(),
                'referer_host' => $this->refererHost($request->headers->get('referer')),
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
                'locale' => app()->getLocale(),
                'visited_at' => Carbon::now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to record public access log.', ['exception' => $exception]);

            return null;
        }
    }

    /**
     * Aggregate accesses by region for the visitor map.
     *
     * @return Collection<int, array{country_code: string|null, country: string|null, region: string|null, latitude: float|null, longitude: float|null, visits: int}>
     */
    public function visitorMap(?Carbon $since = null, ?string $churchId = null): Collection
    {
        return PublicAccessLog::query()
            ->when($since !== null, fn ($query) => $query->where('visited_at', '>=', $since))
            ->when($churchId !== null, fn ($query) => $query->where('church_id', $churchId))
            ->whereNotNull('country_code')
            ->selectRaw('country_code, max(country) as country, region, avg(latitude) as latitude, avg(longitude) as longitude, count(*) as visits')
            ->groupBy('country_code', 'region')
            ->orderByDesc('visits')
            ->get()
            ->map(fn ($row): array => [
                'country_code' => $row->country_code,
                'country' => $row->country,
                'region' => $row->region,
                'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
                'visits' => (int) $row->visits,
            ]);
    }

    /**
     * One-way hash of the visitor IP using the application key as salt so that
     * individual visitors cannot be re-identified from stored logs.
     */
    private function hashIp(?string $ip): ?string
    {
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function refererHost(?string $referer): ?string
    {
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
