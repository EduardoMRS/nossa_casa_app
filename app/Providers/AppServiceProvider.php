<?php

namespace App\Providers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\NativePushService;
use App\Services\Push\ApnsPushProvider;
use App\Services\Push\FcmPushProvider;
use App\Services\Push\NullPushProvider;
use App\Services\Push\UnifiedPushProvider;
use App\Services\Push\WebPushProvider;
use App\Support\ChurchContext;
use App\Support\ChurchDomainContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ChurchDomainContext::class);
        $this->app->alias(ChurchDomainContext::class, ChurchContext::class);
        $this->app->singleton(NativePushService::class, fn ($app): NativePushService => new NativePushService([
            $app->make(ApnsPushProvider::class),
            $app->make(FcmPushProvider::class),
            $app->make(UnifiedPushProvider::class),
            $app->make(WebPushProvider::class),
            $app->make(NullPushProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTemporaryStorageUrls();
        $this->configureExceptionPages();
        $this->configureMobileRateLimiting();
    }

    protected function configureExceptionPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            $status = $response->statusCode();

            if (
                $response->request->is('api/*')
                || $response->request->expectsJson()
                || (app()->environment('local') && (bool) config('app.debug'))
                || $status < 400
                || $status > 599
            ) {
                return null;
            }

            $domainContext = $this->app->make(ChurchDomainContext::class);

            if (! $domainContext->isResolved()) {
                $domainContext->resolve($response->request, failWhenUnknown: false);
            }

            return $response
                ->render('ErrorPage', ['status' => $status])
                ->usingMiddleware(HandleInertiaRequests::class)
                ->withSharedData();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureTemporaryStorageUrls(): void
    {
        $diskNames = config('filesystems.temporary_url_disks', []);

        if (! is_array($diskNames)) {
            return;
        }

        foreach ($diskNames as $diskName) {
            if (! is_string($diskName) || ! is_array(config("filesystems.disks.{$diskName}"))) {
                continue;
            }

            $disk = Storage::disk($diskName);

            if ($disk->providesTemporaryUrls()) {
                continue;
            }

            $disk->buildTemporaryUrlsUsing(
                fn (string $path, DateTimeInterface $expiration, array $options): string => temporaryStorageUrl(
                    $path,
                    $expiration,
                    $diskName,
                ),
            );
        }
    }

    protected function configureMobileRateLimiting(): void
    {
        RateLimiter::for('mobile-login', function (Request $request): Limit {
            $key = Str::lower($request->string('email')->toString())
                .'|'.$request->string('device_id')->toString()
                .'|'.$request->ip();

            return $this->mobileLimit(5, hash('sha256', $key));
        });

        RateLimiter::for('mobile-refresh', function (Request $request): Limit {
            $key = $request->string('refresh_token')->toString().'|'.$request->ip();

            return $this->mobileLimit(10, hash('sha256', $key));
        });

        RateLimiter::for('mobile-authenticated', function (Request $request): Limit {
            return $this->mobileLimit(60, (string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('discovery', function (Request $request): Limit {
            return $this->mobileLimit(30, (string) $request->ip());
        });

        RateLimiter::for('push-gateway', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                (string) ($request->header('X-Push-Instance-ID') ?? $request->ip()),
            );
        });
    }

    private function mobileLimit(int $attempts, string $key): Limit
    {
        return Limit::perMinute($attempts)
            ->by($key)
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => __('auth.mobile.rate_limited'),
                'code' => 'RATE_LIMITED',
            ], 429, $headers));
    }
}
