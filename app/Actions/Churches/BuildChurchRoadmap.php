<?php

namespace App\Actions\Churches;

use App\Models\Church;
use App\Models\Event;
use App\Models\Library;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BuildChurchRoadmap
{
    /**
     * @return array<int, array{id: string, type: string, title: string, description: string, date: string|null, href: string}>
     */
    public function handle(Church $church, bool $canPreviewPosts): array
    {
        $events = Event::query()
            ->whereBelongsTo($church)
            ->where('start_time', '>=', now()->startOfDay())
            ->orderBy('start_time')
            ->limit(4)
            ->get(['id', 'title', 'slug', 'description', 'start_time'])
            ->map(function (Event $event): array {
                $event->localize();

                return [
                    'id' => 'event-'.$event->id,
                    'type' => 'event',
                    'title' => $event->title,
                    'description' => $this->excerpt($event->description),
                    'date' => $event->start_time?->toIso8601String(),
                    'href' => route('events.show', $event),
                ];
            });
        $posts = Post::query()
            ->whereBelongsTo($church)
            ->when(
                $canPreviewPosts,
                fn ($query) => $query->visible(),
                fn ($query) => $query->published(),
            )
            ->latest('published_at')
            ->latest('created_at')
            ->limit(4)
            ->get(['id', 'title', 'slug', 'content', 'published_at', 'created_at', 'church_id'])
            ->map(function (Post $post): array {
                $post->localize();

                return [
                    'id' => 'post-'.$post->id,
                    'type' => 'post',
                    'title' => $post->title,
                    'description' => $this->excerpt($post->content, markdown: true),
                    'date' => ($post->published_at ?? $post->created_at)?->toIso8601String(),
                    'href' => route('posts.public.show', $post->slug),
                ];
            });
        $libraryItems = Library::query()
            ->whereBelongsTo($church)
            ->latest()
            ->limit(4)
            ->get(['id', 'title', 'description', 'created_at', 'church_id'])
            ->map(function (Library $library): array {
                $library->localize();

                return [
                    'id' => 'library-'.$library->id,
                    'type' => 'library',
                    'title' => $library->title,
                    'description' => $this->excerpt($library->description),
                    'date' => $library->created_at?->toIso8601String(),
                    'href' => route('library.index'),
                ];
            });

        return $this->interleave([$events, $posts, $libraryItems]);
    }

    /**
     * @param  array<int, Collection<int, array<string, mixed>>>  $streams
     * @return array<int, array<string, mixed>>
     */
    private function interleave(array $streams): array
    {
        $items = collect();
        $maximumSize = collect($streams)->max(fn (Collection $stream): int => $stream->count()) ?? 0;

        for ($index = 0; $index < $maximumSize; $index++) {
            foreach ($streams as $stream) {
                if ($stream->has($index)) {
                    $items->push($stream->get($index));
                }
            }
        }

        return $items->take(9)->values()->all();
    }

    private function excerpt(?string $content, bool $markdown = false): string
    {
        $plainText = $markdown
            ? Str::markdown((string) $content, ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : (string) $content;

        return Str::limit((string) preg_replace('/\s+/', ' ', trim(strip_tags($plainText))), 150);
    }
}
