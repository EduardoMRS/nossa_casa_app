<?php

namespace App\Services;

use App\Models\ContentCache;
use App\Models\ServerProfile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final readonly class OfflineContentService
{
    /** @var array<string, array{path: string, ttl: int}> */
    private const PUBLIC_RESOURCES = [
        'portal' => ['path' => 'portal', 'ttl' => 3600],
        'posts' => ['path' => 'content/posts', 'ttl' => 21600],
        'events' => ['path' => 'content/events', 'ttl' => 21600],
        'library' => ['path' => 'content/library', 'ttl' => 86400],
    ];

    public function __construct(private NativeApiClient $api) {}

    /** @return array<string, bool> */
    public function syncPublic(ServerProfile $server): array
    {
        $results = [];

        foreach (self::PUBLIC_RESOURCES as $key => $resource) {
            try {
                $this->store($server, $key, $this->api->get($server, $resource['path']), $resource['ttl']);
                $results[$key] = true;
            } catch (\Throwable $exception) {
                report($exception);
                $results[$key] = false;
            }
        }

        return $results;
    }

    public function downloadBible(ServerProfile $server, string $version): ContentCache
    {
        $version = trim($version);

        if (preg_match('/^[A-Za-z0-9._-]{2,20}$/', $version) !== 1) {
            throw new InvalidArgumentException('Invalid Bible version identifier.');
        }

        return $this->store(
            $server,
            'bible:'.strtolower($version),
            $this->api->get($server, 'bible/'.rawurlencode($version).'/offline'),
            2592000,
        );
    }

    /** @return list<array{key: string, refreshed_at: ?string, expires_at: ?string, stale: bool}> */
    public function summary(ServerProfile $server): array
    {
        return $server->caches()->orderBy('cache_key')->get()->map(fn (ContentCache $cache): array => [
            'key' => $cache->cache_key,
            'refreshed_at' => $cache->refreshed_at?->toIso8601String(),
            'expires_at' => $cache->expires_at?->toIso8601String(),
            'stale' => $cache->expires_at?->isPast() ?? true,
        ])->all();
    }

    /** @param array<string, mixed> $payload */
    private function store(ServerProfile $server, string $key, array $payload, int $ttl): ContentCache
    {
        return $server->caches()->updateOrCreate(['cache_key' => $key], [
            'payload' => $payload,
            'refreshed_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addSeconds($ttl),
        ]);
    }
}
