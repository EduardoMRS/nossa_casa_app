<?php

namespace App\Providers;

use App\Services\NativePushRegistrationService;
use App\Services\NativeSessionService;
use App\Services\ServerDiscoveryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Events\PushNotification\TokenGenerated;
use NativePHP\Vibe\Facades\Vibe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vibe::resolveTokenUsing(function (): ?string {
            $server = app(ServerDiscoveryService::class)->selected();
            $session = $server ? app(NativeSessionService::class)->get($server) : null;

            return is_array($session) && is_string($session['access_token'] ?? null)
                ? $session['access_token']
                : null;
        });

        Event::listen(TokenGenerated::class, function (TokenGenerated $event): void {
            app(NativePushRegistrationService::class)->registerGeneratedToken($event->token);
        });
    }
}
