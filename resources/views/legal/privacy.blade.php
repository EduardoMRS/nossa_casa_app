@php
    $locales = collect(config('app.locales', ['en']))
        ->map(fn (string $locale): string => mb_strtolower(explode('-', str_replace('_', '-', $locale))[0]))
        ->unique()
        ->values();
    $canonicalUrl = url()->current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('legal.title') }} · {{ config('app.name') }}</title>
        <meta name="description" content="{{ __('legal.meta_description') }}">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        @foreach ($locales as $locale)
            <link rel="alternate" hreflang="{{ $locale }}" href="{{ route('legal.privacy', ['locale' => $locale]) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ route('legal.privacy', ['locale' => $locales->first()]) }}">
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <header class="border-b border-indigo-900/60 bg-[#312e81] text-white">
            <div class="mx-auto flex min-h-16 max-w-4xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" class="font-semibold tracking-tight">
                    {{ config('app.name') }}
                </a>
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm text-indigo-100 transition hover:bg-white/10 hover:text-white">
                    {{ __('legal.back_to_portal') }}
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:py-16">
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                <p class="mb-3 text-sm font-semibold tracking-wide text-indigo-700 uppercase">{{ __('legal.kicker') }}</p>
                <h1 class="text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{{ __('legal.title') }}</h1>
                <p class="mt-4 text-sm text-slate-500">{{ __('legal.updated_at') }}</p>
                <p class="mt-8 text-base leading-7 text-slate-700">{{ __('legal.introduction') }}</p>

                <div class="mt-10 space-y-10">
                    @foreach (__('legal.sections') as $section)
                        <section>
                            <h2 class="text-xl font-semibold text-slate-950">{{ $section['title'] }}</h2>
                            <div class="mt-3 space-y-4 text-base leading-7 text-slate-700">
                                @foreach ($section['paragraphs'] ?? [] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                                @if (filled($section['items'] ?? []))
                                    <ul class="list-disc space-y-2 pl-6">
                                        @foreach ($section['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            </article>
        </main>

        <footer class="border-t border-slate-200 bg-white py-6 text-center text-sm text-slate-500">
            <p>{{ __('common.copyright') }}</p>
        </footer>
    </body>
</html>
