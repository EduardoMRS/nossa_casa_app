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

        // Já existe um locale válido na URL
        if ($locale && $locales->contains(mb_strtolower($locale))) {
            app()->setLocale($locale);

            return $next($request);
        }

        // Não existe locale na URL.
        // Descobre pelo locale atual do Laravel / navegador.
        $locale = $this->resolveLocale($request, $locales);

        // Evita duplicar locale caso a rota já esteja tratando isso.
        $path = ltrim($request->path(), '/');

        if ($locale && $path !== '') {
            return redirect()->to("/{$locale}/{$path}");
        }

        return redirect()->to("/{$locale}");
    }

    protected function resolveLocale(Request $request, $locales): string
    {
        // 1. Locale já definido pela aplicação
        $current = app()->getLocale();

        if ($locales->contains(mb_strtolower($current))) {
            return mb_strtolower($current);
        }

        // 2. Locale do navegador
        $browserLocale = $request->getPreferredLanguage(
            $locales->all()
        );

        if ($browserLocale) {
            return mb_strtolower($browserLocale);
        }

        // 3. Fallback
        return mb_strtolower(
            explode('-', explode('_', config('app.locale'))[0])[0]
        );
    }
}
