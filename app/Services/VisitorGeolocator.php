<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves an approximate geographic origin from an IP address.
 *
 * The lookup is best-effort: private, reserved or local addresses and any
 * provider failure resolve to an empty result so that logging a public access
 * never blocks the request. Results are cached per IP to avoid repeated calls.
 */
class VisitorGeolocator
{
    /**
     * Resolve the approximate origin for the given IP address.
     *
     * @return array{country_code: string|null, country: string|null, region: string|null, city: string|null, latitude: float|null, longitude: float|null}
     */
    public function locate(?string $ip): array
    {
        if (! $this->isPublicIp($ip)) {
            return $this->emptyLocation();
        }

        if (! config('services.ip_geolocation.enabled')) {
            return $this->emptyLocation();
        }

        $ttl = (int) config('services.ip_geolocation.cache_ttl', 86400);

        return Cache::remember(
            'visitor-geo:'.$ip,
            $ttl,
            fn (): array => $this->lookup((string) $ip),
        );
    }

    /**
     * @return array{country_code: string|null, country: string|null, region: string|null, city: string|null, latitude: float|null, longitude: float|null}
     */
    private function lookup(string $ip): array
    {
        try {
            $url = rtrim((string) config('services.ip_geolocation.url'), '/').'/'.$ip;

            $response = Http::timeout(4)
                ->connectTimeout(3)
                ->get($url, [
                    'fields' => 'status,countryCode,country,regionName,city,lat,lon',
                ]);

            if (! $response->successful() || $response->json('status') !== 'success') {
                return $this->emptyLocation();
            }

            $latitude = filter_var($response->json('lat'), FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($response->json('lon'), FILTER_VALIDATE_FLOAT);

            return [
                'country_code' => $this->nullableString($response->json('countryCode')),
                'country' => $this->nullableString($response->json('country')),
                'region' => $this->nullableString($response->json('regionName')),
                'city' => $this->nullableString($response->json('city')),
                'latitude' => $latitude === false ? null : (float) $latitude,
                'longitude' => $longitude === false ? null : (float) $longitude,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Visitor geolocation failed.', ['exception' => $exception]);

            return $this->emptyLocation();
        }
    }

    private function isPublicIp(?string $ip): bool
    {
        if (! is_string($ip) || $ip === '') {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return ($value === null || $value === '') ? null : $value;
    }

    /**
     * @return array{country_code: null, country: null, region: null, city: null, latitude: null, longitude: null}
     */
    private function emptyLocation(): array
    {
        return [
            'country_code' => null,
            'country' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
