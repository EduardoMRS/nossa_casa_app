@php
    $branding = data_get($page, 'props.branding', []);
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

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        <meta name="theme-color" content="{{ $validColor(data_get($branding, 'primary_color'), '#342f87') }}">

        @unless(app()->environment('testing'))
            @fonts
        @endunless

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
