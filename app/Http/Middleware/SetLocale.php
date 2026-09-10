<?php

namespace App\Http\Middleware;

use App\Support\ChurchDomainContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = collect(config('app.locales', ['en']))
            ->map(fn (string $locale): string => $this->normalizeLocale($locale))
            ->unique()
            ->values();

        $churchDefaultLocale = app(ChurchDomainContext::class)
            ->church()
            ?->community
            ?->default_locale;
        $requestedLocale = $request->header('X-Locale')
            ?? $request->cookie('ncapp_locale')
            ?? $request->user()?->profile?->location_lang
            ?? $churchDefaultLocale
            ?? $request->getPreferredLanguage($supportedLocales->all())
            ?? config('app.locale');
        $locale = $this->normalizeLocale((string) $requestedLocale);

        if ($supportedLocales->contains($locale)) {
            App::setLocale($locale);
        }

        // URL::defaults([
        //     'locale' => $locale,
        // ]);

        return $next($request);
    }

    private function normalizeLocale(string $locale): string
    {
        return mb_strtolower(explode('-', str_replace('_', '-', trim($locale)))[0]);
    }
}
