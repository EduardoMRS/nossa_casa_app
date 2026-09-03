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
        $sitemapUrl = $request->getSchemeAndHttpHost().route('sitemap', absolute: false);
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
        $urls = collect([
            ['path' => '/', 'last_modified' => null],
            ['path' => route('login', absolute: false), 'last_modified' => null],
            ['path' => route('register', absolute: false), 'last_modified' => null],
            ['path' => route('password.request', absolute: false), 'last_modified' => null],
            ['path' => route('legal.privacy', absolute: false), 'last_modified' => null],
        ])->map(fn (array $url): array => [
            'location' => $portalUrl.$url['path'],
            'last_modified' => $url['last_modified'],
        ]);

        Community::query()
            ->whereHas('churches', fn ($query) => $query->where('status', ChurchStatus::ACTIVE))
            ->oldest('created_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Community $community) use (&$urls, $portalUrl): void {
                $urls->push([
                    'location' => $portalUrl.'/'.route('communities.show', $community, absolute: false),
                    'last_modified' => $community->updated_at?->toAtomString(),
                ]);
            });

        Church::query()
            ->where('status', ChurchStatus::ACTIVE)
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->oldest('created_at')
            ->get(['domain', 'updated_at'])
            ->each(function (Church $church) use (&$urls, $context): void {
                $urls->push([
                    'location' => $context->churchUrl($church),
                    'last_modified' => $church->updated_at?->toAtomString(),
                ]);
            });

        return $urls->all();
    }

    /** @return array<int, array{location: string, last_modified: string|null}> */
    private function churchUrls(Request $request, Church $church): array
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $urls = collect([
            ['path' => '/', 'last_modified' => $church->updated_at?->toAtomString()],
            ['path' => route('events.index', absolute: false), 'last_modified' => null],
            ['path' => route('posts.public.index', absolute: false), 'last_modified' => null],
            ['path' => route('library.index', absolute: false), 'last_modified' => null],
            ['path' => route('library.bible', absolute: false), 'last_modified' => null],
            ['path' => route('gallery.index', absolute: false), 'last_modified' => null],
            ['path' => route('login', absolute: false), 'last_modified' => null],
            ['path' => route('register', absolute: false), 'last_modified' => null],
            ['path' => route('password.request', absolute: false), 'last_modified' => null],
            ['path' => route('legal.privacy', absolute: false), 'last_modified' => null],
        ])->map(fn (array $url): array => [
            'location' => $baseUrl.$url['path'],
            'last_modified' => $url['last_modified'],
        ]);

        Event::query()
            ->whereBelongsTo($church)
            ->oldest('start_time')
            ->get(['slug', 'updated_at'])
            ->each(function (Event $event) use ($urls, $baseUrl): void {
                $urls->push([
                    'location' => $baseUrl.'/'.route('events.show', $event, absolute: false),
                    'last_modified' => $event->updated_at?->toAtomString(),
                ]);
            });

        Post::query()
            ->whereBelongsTo($church)
            ->published()
            ->latest('published_at')
            ->get(['slug', 'updated_at'])
            ->each(function (Post $post) use ($urls, $baseUrl): void {
                $urls->push([
                    'location' => $baseUrl.'/'.route('posts.public.show', $post->slug, absolute: false),
                    'last_modified' => $post->updated_at?->toAtomString(),
                ]);
            });

        return $urls->all();
    }
}
