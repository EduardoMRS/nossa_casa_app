<?php

namespace App\Services;

use App\Models\ServerProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServerDiscoveryService
{
    public function discover(string $input): ServerProfile
    {
        $origin = $this->normalize($input);

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('nossa_casa.discovery_timeout'))
                ->withoutRedirecting()
                ->get($origin.'/.well-known/nossa-casa.json');
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'server' => [__('native.errors.server_unreachable')],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'server' => [__('native.errors.discovery_failed')],
            ]);
        }

        $discovery = $response->json();
        $apiBaseUrl = is_array($discovery) ? ($discovery['api_base_url'] ?? null) : null;
        $webBaseUrl = is_array($discovery) ? ($discovery['web_base_url'] ?? null) : null;
        $realtime = is_array($discovery) && is_array($discovery['realtime'] ?? null)
            ? $discovery['realtime']
            : null;

        if (
            ! is_array($discovery)
            || ($discovery['protocol'] ?? null) !== 'nossa-casa'
            || ($discovery['protocol_version'] ?? null) !== 1
            || ! is_string($discovery['instance_id'] ?? null)
            || ! Str::isUlid($discovery['instance_id'])
            || ! is_string($apiBaseUrl)
            || ! is_string($webBaseUrl)
            || $this->origin($apiBaseUrl) !== $origin
            || $this->origin($webBaseUrl) !== $origin
            || ! str_starts_with(parse_url($apiBaseUrl, PHP_URL_PATH) ?: '/', '/api')
        ) {
            throw ValidationException::withMessages([
                'server' => [__('native.errors.invalid_discovery')],
            ]);
        }

        return DB::transaction(function () use ($discovery, $origin, $apiBaseUrl, $webBaseUrl, $realtime): ServerProfile {
            ServerProfile::query()->update(['selected' => false]);

            return ServerProfile::query()->updateOrCreate(
                ['instance_id' => $discovery['instance_id']],
                [
                    'name' => (string) ($discovery['instance_name'] ?? $origin),
                    'origin' => $origin,
                    'api_base_url' => rtrim($apiBaseUrl, '/'),
                    'web_base_url' => rtrim($webBaseUrl, '/'),
                    'api_version' => (int) ($discovery['api_version'] ?? 1),
                    'capabilities' => array_values(array_filter(
                        $discovery['capabilities'] ?? [],
                        fn (mixed $capability): bool => is_string($capability),
                    )),
                    'realtime' => $this->validRealtime($realtime, $origin) ? $realtime : null,
                    'selected' => true,
                    'last_used_at' => now(),
                ],
            );
        });
    }

    public function selected(): ?ServerProfile
    {
        return ServerProfile::query()->where('selected', true)->first();
    }

    private function normalize(string $input): string
    {
        $input = trim($input);
        $qr = json_decode($input, true);

        if (is_array($qr)) {
            $input = (string) ($qr['server_url'] ?? $qr['url'] ?? '');
        }

        if (! str_contains($input, '://')) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if (
            ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '/', ['', '/'], true)
        ) {
            throw ValidationException::withMessages([
                'server' => [__('native.errors.invalid_server_url')],
            ]);
        }

        return 'https://'.Str::lower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return Str::lower($parts['scheme']).'://'.Str::lower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** @param array<string, mixed>|null $realtime */
    private function validRealtime(?array $realtime, string $origin): bool
    {
        if ($realtime === null || ($realtime['enabled'] ?? false) !== true) {
            return false;
        }

        $expectedHost = parse_url($origin, PHP_URL_HOST);
        $scheme = $realtime['scheme'] ?? null;

        return is_string($realtime['key'] ?? null)
            && $realtime['key'] !== ''
            && is_string($realtime['host'] ?? null)
            && Str::lower($realtime['host']) === Str::lower((string) $expectedHost)
            && in_array($scheme, ['https', 'wss'], true)
            && is_int($realtime['port'] ?? null)
            && ($realtime['port'] >= 1 && $realtime['port'] <= 65535)
            && ($realtime['auth_path'] ?? null) === '/api/broadcasting/auth';
    }
}
