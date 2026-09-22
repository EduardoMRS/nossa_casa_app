<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocaleInUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = collect(config('app.locales'))
            ->map(fn ($locale) => mb_strtolower(explode('-', explode('_', $locale)[0])[0]))
            ->values();

        $locale = $request->route('locale');

        if ($locale && $locales->contains(mb_strtolower($locale))) {
            app()->setLocale($locale);

            return $next($request);
        }

        $locale = $this->resolveLocale($request, $locales);
        $path = ltrim($request->path(), '/');

        return redirect()->to('/'.$locale.($path !== '' ? '/'.$path : ''));
    }

    protected function resolveLocale(Request $request, $locales): string
    {
        $browserLocale = $request->getPreferredLanguage(
            $locales->all()
        );

        if ($browserLocale) {
            return mb_strtolower($browserLocale);
        }

        return mb_strtolower(
            explode('-', explode('_', config('app.locale'))[0])[0]
        );
    }
}
