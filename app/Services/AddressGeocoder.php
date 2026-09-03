<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddressGeocoder
{
    /**
     * @param  array{country?: string|null, state?: string|null, city?: string|null, neighborhood?: string|null, street?: string|null, number?: string|null, complement?: string|null, zipcode?: string|null}  $address
     * @return array{latitude: float, longitude: float}|null
     */
    public function coordinates(array $address): ?array
    {
        $query = $this->query($address);

        if (! config('services.geocoding.enabled') || blank($query)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('services.geocoding.user_agent'),
            ])
                ->retry([100, 500], throw: false)
                ->timeout(5)
                ->connectTimeout(3)
                ->get((string) config('services.geocoding.url'), [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json('0');
            $latitude = is_array($result) ? filter_var($result['lat'] ?? null, FILTER_VALIDATE_FLOAT) : false;
            $longitude = is_array($result) ? filter_var($result['lon'] ?? null, FILTER_VALIDATE_FLOAT) : false;

            return $latitude !== false && $longitude !== false
                ? ['latitude' => (float) $latitude, 'longitude' => (float) $longitude]
                : null;
        } catch (\Throwable $exception) {
            Log::warning('Address geocoding failed.', ['exception' => $exception]);

            return null;
        }
    }

    /** @param  array<string, string|null>  $address */
    private function query(array $address): string
    {
        return collect([
            $address['street'] ?? null,
            $address['number'] ?? null,
            $address['neighborhood'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zipcode'] ?? null,
            $address['country'] ?? null,
        ])->filter(fn (?string $value): bool => filled($value))->implode(', ');
    }
}