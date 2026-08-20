<?php

namespace App\Providers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ChurchDomainContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
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
        $this->app->singleton(ChurchDomainContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTemporaryStorageUrls();
        $this->configureExceptionPages();
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
}
