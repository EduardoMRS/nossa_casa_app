<?php

namespace App\Providers;

use App\Support\ChurchDomainContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
