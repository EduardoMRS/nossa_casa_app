<?php

namespace App\Queries;

use App\Data\CanonicalData;
use App\Models\Church;
use App\Models\Event;
use App\Models\Highlight;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Bible\BibleAccessResolver;
use Illuminate\Support\Str;

final readonly class PortalQuery
{
    public function __construct(private BibleAccessResolver $bibleAccess) {}

    public function churchHome(Church $church, ?User $user): CanonicalData
    {
        $churchId = $church->id;
        $role = $user?->role?->value ?? (string) $user?->role;
        $canPreviewPosts = $user !== null
            && ($role === 'system' || $user->churches()->whereKey($churchId)->exists())
            && in_array($role, ['leader', 'media', 'church_leader', 'superadmin', 'system'], true);
        $eventHighlightOrder = Highlight::query()
            ->where('church_id', $churchId)
            ->where('highlightable_type', Event::class)
            ->orderBy('order')
            ->pluck('highlightable_id')
            ->flip();
        $featuredEvents = Event::query()
            ->where('church_id', $churchId)
            ->where('start_time', '>=', now()->startOfDay())
            ->with('church:id,name,slug,domain')
            ->orderBy('start_time')
            ->limit(30)
            ->get()
            ->sortBy(fn (Event $event): int => $eventHighlightOrder->get($event->id, PHP_INT_MAX))
            ->take(3)
            ->map(function (Event $event): array {
                $event->localize(relations: ['church']);

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'excerpt' => Str::limit(strip_tags((string) $event->description), 120),
                    'cover_path' => $event->cover_url,
                    'start_time' => $event->start_time?->toISOString(),
                    'church' => [
                        'id' => $event->church->id,
                        'name' => $event->church->name,
                        'slug' => $event->church->slug,
                    ],
                ];
            })->values()->all();
        $postHighlightOrder = Highlight::query()
            ->where('church_id', $churchId)
            ->where('highlightable_type', Post::class)
            ->orderBy('order')
            ->pluck('highlightable_id')
            ->flip();
        $latestPosts = Post::query()
            ->where('church_id', $churchId)
            ->when($canPreviewPosts, fn ($query) => $query->visible(), fn ($query) => $query->published())
            ->latest('published_at')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->sortBy(fn (Post $post): int => $postHighlightOrder->get($post->id, PHP_INT_MAX))
            ->take(3)
            ->map(function (Post $post): array {
                $post->localize();

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => Str::limit((string) str(strip_tags(Str::markdown((string) $post->content, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ])))->squish(), 120),
                    'published_at' => $post->published_at?->toISOString(),
                ];
            })->values()->all();
        $calendarEvents = Event::query()
            ->where('church_id', $churchId)
            ->whereBetween('start_time', [now()->subYear()->startOfMonth(), now()->addYear()->endOfMonth()])
            ->orderBy('start_time')
            ->get(['id', 'title', 'slug', 'start_time', 'end_time'])
            ->map(function (Event $event): array {
                $event->localize();

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'start_time' => $event->start_time?->toISOString(),
                    'end_time' => $event->end_time?->toISOString(),
                ];
            })->all();
        $latestRecordings = Media::query()
            ->where('church_id', $churchId)
            ->visible()
            ->where('gallery', true)
            ->whereHas('recording')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'title' => $media->title,
                'url' => $media->url,
                'mimetype' => $media->mimetype,
                'created_at' => $media->created_at?->toISOString(),
            ])->all();
        $verse = data_get($church->settings?->options, 'bible.daily_verse');
        $dailyVerse = is_array($verse)
            && in_array($verse['version'] ?? null, $this->bibleAccess->forChurch($church)['versions'], true)
                ? $verse
                : null;
        $liveStream = LiveStream::query()
            ->where('church_id', $churchId)
            ->whereNotNull('active_slot')
            ->latest('updated_at')
            ->first();

        return new CanonicalData([
            'stats' => [
                'events' => Event::query()->where('church_id', $churchId)->where('start_time', '>=', now()->startOfDay())->count(),
                'gallery' => Media::query()->where('church_id', $churchId)->visible()->count(),
                'posts' => Post::query()
                    ->where('church_id', $churchId)
                    ->when($canPreviewPosts, fn ($query) => $query->visible(), fn ($query) => $query->published())
                    ->count(),
            ],
            'featuredEvents' => $featuredEvents,
            'latestPosts' => $latestPosts,
            'latestRecordings' => $latestRecordings,
            'calendarEvents' => $calendarEvents,
            'dailyVerse' => $dailyVerse,
            'liveStream' => $liveStream ? [
                'id' => $liveStream->id,
                'name' => $liveStream->name,
                'status' => $liveStream->status->value,
                'is_public' => (bool) $liveStream->is_public,
                'embed_url' => $liveStream->embed_url,
            ] : null,
        ]);
    }
}
