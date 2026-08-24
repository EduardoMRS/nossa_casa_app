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
    $seoName = $communityName ?: ($churchName ?: (data_get($branding, 'brand_name') ?: config('app.name')));
    $seoDescription = data_get($page, 'props.community.description')
        ?: (data_get($branding, 'tagline') ?: data_get($branding, 'banner_subtitle'));
    $seoDescription = is_string($seoDescription) && $seoDescription !== ''
        ? $seoDescription
        : __('pwa.description', ['name' => $seoName]);
    $absoluteLogoUrl = str_starts_with((string) $logoUrl, 'http')
        ? $logoUrl
        : request()->getSchemeAndHttpHost().'/'.ltrim((string) $logoUrl, '/');
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
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="{{ $brandingStyle }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $seoName }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $absoluteLogoUrl }}">
        @if ($organizationSchema)
            <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif

        @if (! app()->environment('testing') && str_contains(strtolower($fontFamily), 'instrument sans'))
            @fonts
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
