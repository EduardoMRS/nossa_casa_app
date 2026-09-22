@php
    $branding = data_get($page, 'props.branding', []);
    $logoUrl = data_get($branding, 'logo_url', route('branding.logo', absolute: false));
    $iconUrl = data_get($branding, 'icon_url', route('branding.icon', absolute: false));
    $validColor = static fn (mixed $value, string $fallback): string => is_string($value) && preg_match('/^#[A-Fa-f0-9]{6}$/', $value)
        ? $value
        : $fallback;
    $fontFamily = data_get($branding, 'font_family', 'Manrope, ui-sans-serif');
    $fontFamily = is_string($fontFamily) && preg_match('/^[A-Za-z0-9\s,\'"_-]+$/u', $fontFamily)
        ? $fontFamily
        : 'Manrope, ui-sans-serif';
    $brandingStyle = implode('; ', [
        '--church-primary: '.$validColor(data_get($branding, 'primary_color'), '#342f87'),
        '--church-secondary: '.$validColor(data_get($branding, 'secondary_color'), '#5f7d95'),
        '--church-accent: '.$validColor(data_get($branding, 'accent_color'), '#c88b4a'),
        '--church-surface: '.$validColor(data_get($branding, 'surface_color'), '#f8fafc'),
        '--church-font: '.$fontFamily,
        '--font-sans: '.$fontFamily,
    ]);
    $component = data_get($page, 'component', '');
    $locales = collect(config('app.locales', ['en']))
        ->map(fn (string $locale): string => mb_strtolower(explode('-', str_replace('_', '-', $locale))[0]))
        ->unique()
        ->values();
    $indexableComponents = [
        'Home',
        'Portal/Index',
        'Portal/CommunityShow',
        'Events/Index',
        'Events/Show',
        'Posts/PublicIndex',
        'Posts/PublicShow',
        'Gallery/Index',
        'Library/Index',
        'Library/Bible',
        'LiveStreams/Show',
    ];
    $isIndexable = in_array($component, $indexableComponents, true);
    $canonicalUrl = url()->current();
    $churchName = data_get($page, 'props.churchContext.church.name');
    $communityName = data_get($page, 'props.community.name');
    $contentTitle = data_get($page, 'props.post.title') ?: data_get($page, 'props.event.title');
    $sectionTitle = match ($component) {
        'Events/Index' => __('events.index.meta_title'),
        'Posts/PublicIndex' => __('posts.public.meta_title'),
        'Gallery/Index' => __('gallery.meta_title'),
        'Library/Index', 'Library/Bible' => __('library.title'),
        default => null,
    };
    $seoName = $contentTitle ?: ($communityName ?: ($sectionTitle ? ($churchName ? $sectionTitle.' - '.$churchName : $sectionTitle) : ($churchName ?: (data_get($branding, 'brand_name') ?: config('app.name')))));
    $contentDescription = data_get($page, 'props.post.contentHtml')
        ?: data_get($page, 'props.event.description')
        ?: data_get($page, 'props.event.description_html')
        ?: data_get($page, 'props.community.description');
    $contentDescription = is_string($contentDescription)
        ? trim(preg_replace('/\s+/', ' ', strip_tags($contentDescription)))
        : null;
    $seoDescription = $contentDescription
        ?: (data_get($branding, 'tagline') ?: data_get($branding, 'banner_subtitle'));
    $seoDescription = is_string($seoDescription) && $seoDescription !== ''
        ? \Illuminate\Support\Str::limit($seoDescription, 160)
        : __('pwa.description', ['name' => $seoName]);
    $contentImageUrl = data_get($page, 'props.post.cover_url')
        ?: data_get($page, 'props.event.cover_path')
        ?: data_get($page, 'props.community.logo_url')
        ?: data_get($page, 'props.media.data.0.url');
    $toAbsoluteUrl = static fn (mixed $value): string => str_starts_with((string) $value, 'http')
        ? (string) $value
        : request()->getSchemeAndHttpHost().'/'.ltrim((string) $value, '/');
    $absoluteLogoUrl = $toAbsoluteUrl($logoUrl);
    $absoluteImageUrl = $contentImageUrl ? $toAbsoluteUrl($contentImageUrl) : $absoluteLogoUrl;
    $organizationSchema = in_array($component, ['Home', 'Portal/Index', 'Portal/CommunityShow'], true)
        ? array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $seoName,
            'url' => $canonicalUrl,
            'logo' => $absoluteLogoUrl,
            'description' => $seoDescription,
            'email' => data_get($branding, 'contact_email'),
            'telephone' => data_get($branding, 'contact_phone'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '')
        : null;


    $currentRoute = request()->route()?->getName();
    $routeParameters = request()->route()?->parameters() ?? [];
    unset($routeParameters['locale']);
    $hasLocalizedRoute = $currentRoute !== null && request()->route()?->uri() !== null
        && str_starts_with(request()->route()->uri(), '{locale}');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="{{ $brandingStyle }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="portal-host" content="{{ parse_url((string) config('app.url'), PHP_URL_HOST) }}">
        <meta name="app-name" content="{{ config('app.name', 'Laravel') }}">

        @if ($hasLocalizedRoute)
            @foreach ($locales as $locale)
                @php
                    try {
                        $localizedUrl = route($currentRoute, [
                            ...$routeParameters,
                            'locale' => $locale,
                        ]);
                    } catch (\Throwable $e) {
                        $localizedUrl = null;
                    }
                @endphp

                @if ($localizedUrl)
                    <link
                        rel="alternate"
                        hreflang="{{ $locale }}"
                        href="{{ $localizedUrl }}"
                    >
                @endif
            @endforeach
            <link rel="alternate" hreflang="x-default" href="{{ route($currentRoute, [...$routeParameters, 'locale' => $locales->first()]) }}">
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="{{ $iconUrl }}" type="image/svg+xml" sizes="any">
        <link rel="apple-touch-icon" href="{{ $logoUrl }}">
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta name="theme-color" content="{{ $validColor(data_get($branding, 'primary_color'), '#342f87') }}">
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="robots" content="{{ $isIndexable ? 'index, follow' : 'noindex, nofollow' }}">
        <meta property="og:type" content="{{ $contentTitle ? 'article' : 'website' }}">
        <meta property="og:title" content="{{ $seoName }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $absoluteImageUrl }}">
        <meta property="og:image:alt" content="{{ $seoName }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoName }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $absoluteImageUrl }}">
        @if ($organizationSchema)
            <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif

        @if (! app()->environment('testing') && str_contains(strtolower($fontFamily), 'instrument sans'))
            @fonts
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $seoName }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
