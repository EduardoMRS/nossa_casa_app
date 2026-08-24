<?php

namespace App\NativeComponents;

use App\Models\ContentCache;
use App\Models\ServerProfile;
use App\Services\NativeApiClient;
use App\Services\NativePushRegistrationService;
use App\Services\NativeRealtimeService;
use App\Services\NativeSessionService;
use App\Services\ServerDiscoveryService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

final class Portal extends NativeComponent
{
    /** @var array<string, mixed> */
    public array $content = [];

    public string $serverName = '';

    public string $error = '';

    public bool $offline = false;

    public bool $stale = false;

    public bool $authenticated = false;

    public bool $realtimeConnected = false;

    private bool $realtimeSubscribed = false;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if (! $server) {
            $this->replace('/server');

            return;
        }

        $this->serverName = $server->name;
        $this->authenticated = app(NativeSessionService::class)->get($server) !== null;

        if ($this->authenticated) {
            app(NativePushRegistrationService::class)->synchronize($server);
        }

        try {
            $this->content = app(NativeApiClient::class)->get($server, 'portal');
            $server->caches()->updateOrCreate(['cache_key' => 'portal'], [
                'payload' => $this->content,
                'refreshed_at' => now(),
                'expires_at' => now()->addSeconds((int) config('nossa_casa.cache_ttl')),
            ]);
            $this->offline = false;
            $this->stale = false;
            $this->error = '';
            $this->subscribeRealtime($server);
        } catch (Throwable $exception) {
            report($exception);
            $cache = ContentCache::query()
                ->where('server_profile_id', $server->id)
                ->where('cache_key', 'portal')
                ->first();

            if ($cache) {
                $this->content = $cache->payload;
                $this->offline = true;
                $this->stale = $cache->expires_at->isPast();

                return;
            }

            $this->error = __('native.errors.portal_unavailable');
        }
    }

    public function realtimeDisconnected(): void
    {
        $this->realtimeConnected = false;
    }

    private function subscribeRealtime(ServerProfile $server): void
    {
        if ($this->realtimeSubscribed || ! is_array($this->content['liveStream'] ?? null)) {
            return;
        }

        $this->realtimeSubscribed = app(NativeRealtimeService::class)->subscribeToLiveStream(
            $server,
            $this->content['liveStream'],
            fn () => $this->load(),
            fn () => $this->realtimeDisconnected(),
        );
        $this->realtimeConnected = $this->realtimeSubscribed;
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function login(): void
    {
        $this->navigate('/login');
    }

    public function churches(): void
    {
        $this->navigate('/church-selector');
    }

    public function logout(): void
    {
        $server = app(ServerDiscoveryService::class)->selected();

        if ($server && app(NativeSessionService::class)->get($server)) {
            app(NativePushRegistrationService::class)->unregister($server);
            app(NativeApiClient::class)->logout($server);
        }

        $this->replace('/login');
    }

    public function changeServer(): void
    {
        $this->replace('/server');
    }

    public function offline(): void
    {
        $this->navigate('/offline');
    }

    public function render(): View
    {
        return view('native.portal');
    }
}
