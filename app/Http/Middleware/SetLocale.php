<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = collect(config('app.locales', ['en']))
            ->map(fn (string $locale): string => $this->normalizeLocale($locale))
            ->unique()
            ->values();

        $requestedLocale = $request->header('X-Locale')
            ?? $request->cookie('ncapp_locale')
            ?? $request->getPreferredLanguage($supportedLocales->all())
            ?? config('app.locale');
        $locale = $this->normalizeLocale((string) $requestedLocale);

        if ($supportedLocales->contains($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function normalizeLocale(string $locale): string
    {
        return mb_strtolower(explode('-', str_replace('_', '-', trim($locale)))[0]);
    }
}
