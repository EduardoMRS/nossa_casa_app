<?php

namespace App\Services;

use App\Models\ServerProfile;
use Closure;
use NativePHP\Vibe\Facades\Vibe;

final class NativeRealtimeService
{
    /** @param array<string, mixed> $liveStream */
    public function subscribeToLiveStream(
        ServerProfile $server,
        array $liveStream,
        Closure $refresh,
        Closure $disconnected,
    ): bool {
        if (! $this->configure($server) || ! is_string($liveStream['id'] ?? null)) {
            return false;
        }

        $channel = 'live-stream.'.$liveStream['id'];
        $subscription = ($liveStream['is_public'] ?? false)
            ? Vibe::channel($channel)
            : Vibe::private($channel);

        $subscription
            ->on('live-stream.updated', fn () => $refresh())
            ->on('live-stream.comments.updated', fn () => $refresh())
            ->onReconnect(fn () => $refresh())
            ->onDisconnect(fn () => $disconnected());

        return true;
    }

    public function configure(ServerProfile $server): bool
    {
        $realtime = $server->realtime;

        if (! is_array($realtime)) {
            return false;
        }

        config()->set('vibe.connection', [
            'key' => $realtime['key'],
            'host' => $realtime['host'],
            'port' => $realtime['port'],
            'scheme' => $realtime['scheme'],
        ]);
        config()->set('vibe.auth.endpoint', rtrim($server->origin, '/').$realtime['auth_path']);

        return true;
    }
}
