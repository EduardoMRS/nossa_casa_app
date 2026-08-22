<?php

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Models\Event;
use App\Models\Post;
use App\Support\ChurchDomainContext;
use App\Support\ContentEmbedRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventPrivateAreaController extends Controller
{
    public function __construct(private readonly ContentEmbedRenderer $embedRenderer) {}

    public function __invoke(Request $request, Event $event): Response
    {
        $churchId = app(ChurchDomainContext::class)->churchId();
        abort_if($churchId && $event->church_id !== $churchId, 404);
        abort_unless($request->user(), 403);
        abort_unless(
            $event->registrations()
                ->where('user_id', $request->user()->id)
                ->where('status', 'confirmed')
                ->exists(),
            403,
        );

        $event->load(['church:id,name,slug', 'address', 'materials', 'medias']);
        $event->localize(relations: ['church']);
        $currency = $event->church?->settings?->options['currency'] ?? 'BRL';

        return Inertia::render('Events/PrivateArea', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'description_html' => $this->embedRenderer->render($event->description ?? '', $event->church_id),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'price' => $event->price,
                'currency' => $currency,
                'cover_path' => $event->cover_url,
                'church' => $event->church,
                'address' => $event->address,
            ],
            'posts' => $event->privatePosts()
                ->where('is_event_private', true)
                ->published()
                ->latest('published_at')
                ->get()
                ->map(fn (Post $post): array => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'content_html' => $this->embedRenderer->render($post->content, $event->church_id),
                    'published_at' => $post->published_at,
                ]),
            'materials' => $event->materials->map(fn ($material): array => [
                'id' => $material->id,
                'title' => $material->title,
                'type' => $material->type,
                'download_url' => $material->download_url,
                'mimetype' => $material->mimetype,
                'size' => $material->size,
            ]),
            'media' => $event->medias
                ->filter(fn ($media): bool => $media->status === MediaStatus::APPROVED)
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'title' => $media->title,
                    'description' => $media->description,
                    'url' => $media->url,
                    'type' => str_starts_with((string) $media->mimetype, 'video/') ? 'video' : 'image',
                    'mimetype' => $media->mimetype,
                    'gallery' => $media->gallery,
                ])->values(),
        ]);
    }
}
