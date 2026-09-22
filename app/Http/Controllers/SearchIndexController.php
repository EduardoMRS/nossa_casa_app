<?php

namespace App\Http\Controllers;

use App\Enums\ChurchStatus;
use App\Models\Church;
use App\Models\Community;
use App\Models\Event;
use App\Models\Post;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SearchIndexController extends Controller
{
    public function sitemap(Request $request, ChurchDomainContext $context): Response
    {
        $urls = $context->church()
            ? $this->churchUrls($request, $context->church())
            : $this->portalUrls($context);

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=900');
    }

    public function robots(Request $request): Response
    {
        $sitemapUrl = $request->getSchemeAndHttpHost().'/sitemap.xml';
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /api/',
            'Disallow: /dashboard',
            'Disallow: /settings',
            'Disallow: /auth/',
            '',
            'Sitemap: '.$sitemapUrl,
            '',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    /** @return array<int, array{location: string, last_modified: string|null}> */
    private function portalUrls(ChurchDomainContext $context): array
    {
        $portalUrl = rtrim((string) config('app.url'), '/');
        $urls = collect($this->locales())->flatMap(fn (string $locale): array => [
            ['location' => $portalUrl.$this->localizedPath('home', $locale), 'last_modified' => null],
            ['location' => $portalUrl.$this->localizedPath('legal.privacy', $locale), 'last_modified' => null],
            ['location' => $portalUrl.$this->localizedPath('login', $locale), 'last_modified' => null],
            ['location' => $portalUrl.$this->localizedPath('register', $locale), 'last_modified' => null],
            ['location' => $portalUrl.$this->localizedPath('password.request', $locale), 'last_modified' => null],
        ]);

        Community::query()
            ->whereHas('churches', fn ($query) => $query->where('status', ChurchStatus::ACTIVE))
            ->oldest('created_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Community $community) use (&$urls, $portalUrl): void {
                foreach ($this->locales() as $locale) {
                    $urls->push([
                        'location' => $portalUrl.$this->localizedPath('communities.show', $locale, ['community' => $community->slug]),
                        'last_modified' => $community->updated_at?->toAtomString(),
                    ]);
                }
            });

        Church::query()
            ->where('status', ChurchStatus::ACTIVE)
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->oldest('created_at')
            ->get(['domain', 'updated_at'])
            ->each(function (Church $church) use (&$urls, $context): void {
                foreach ($this->locales() as $locale) {
                    $urls->push([
                        'location' => $context->churchUrl($church, $locale),
                        'last_modified' => $church->updated_at?->toAtomString(),
                    ]);
                }
            });

        return $urls->all();
    }

    /** @return array<int, array{location: string, last_modified: string|null}> */
    private function churchUrls(Request $request, Church $church): array
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $urls = collect($this->locales())->flatMap(function (string $locale) use ($church): array {
            return collect([
                ['name' => 'home', 'parameters' => [], 'last_modified' => $church->updated_at?->toAtomString()],
                ['name' => 'events.index', 'parameters' => [], 'last_modified' => null],
                ['name' => 'posts.public.index', 'parameters' => [], 'last_modified' => null],
                ['name' => 'library.index', 'parameters' => [], 'last_modified' => null],
                ['name' => 'library.bible', 'parameters' => [], 'last_modified' => null],
                ['name' => 'gallery.index', 'parameters' => [], 'last_modified' => null],
                ['name' => 'legal.privacy', 'parameters' => [], 'last_modified' => null],
                ['name' => 'login', 'parameters' => [], 'last_modified' => null],
                ['name' => 'register', 'parameters' => [], 'last_modified' => null],
                ['name' => 'password.request', 'parameters' => [], 'last_modified' => null],
            ])->map(fn (array $url): array => [
                'location' => $this->localizedPath($url['name'], $locale, $url['parameters']),
                'last_modified' => $url['last_modified'],
            ])->all();
        });

        Event::query()
            ->whereBelongsTo($church)
            ->oldest('start_time')
            ->get(['slug', 'updated_at'])
            ->each(function (Event $event) use (&$urls): void {
                foreach ($this->locales() as $locale) {
                    $urls->push([
                        'location' => $this->localizedPath('events.show', $locale, ['event' => $event->slug]),
                        'last_modified' => $event->updated_at?->toAtomString(),
                    ]);
                }
            });

        Post::query()
            ->whereBelongsTo($church)
            ->published()
            ->latest('published_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Post $post) use (&$urls): void {
                foreach ($this->locales() as $locale) {
                    $urls->push([
                        'location' => $this->localizedPath('posts.public.show', $locale, ['slug' => $post->slug]),
                        'last_modified' => $post->updated_at?->toAtomString(),
                    ]);
                }
            });

        return $urls->map(fn (array $url): array => [
            'location' => $baseUrl.$url['location'],
            'last_modified' => $url['last_modified'],
        ])->all();
    }

    /** @return array<int, string> */
    private function locales(): array
    {
        return collect(config('app.locales', ['en']))
            ->map(fn (string $locale): string => mb_strtolower(explode('-', str_replace('_', '-', $locale))[0]))
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $parameters */
    private function localizedPath(string $name, string $locale, array $parameters = []): string
    {
        return '/'.ltrim(route($name, [...$parameters, 'locale' => $locale], absolute: false), '/');
    }
}
