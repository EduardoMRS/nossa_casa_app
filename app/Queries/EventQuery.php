<?php

namespace App\Queries;

use App\Data\CanonicalData;
use App\Models\Event;
use App\Models\User;
use App\Support\ContentEmbedRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EventQuery
{
    public function __construct(private readonly ContentEmbedRenderer $embedRenderer) {}

    public function index(?string $churchId, int $perPage = 18): CanonicalData
    {
        $events = Event::query()
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->with('church:id,name,slug')
            ->orderBy('start_time')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Event $event): array => $this->item($event));

        return new CanonicalData(['events' => $events->toArray()]);
    }

    public function show(string $slug, ?string $churchId, ?User $user = null): CanonicalData
    {
        $event = Event::query()
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->with(['church:id,name,slug', 'church.settings', 'categories:id,name', 'address'])
            ->where('slug', $slug)
            ->firstOrFail();
        $registrationForm = $event->forms()
            ->select(['forms.id', 'forms.title', 'forms.description'])
            ->first();
        $event->localize(relations: ['church', 'categories']);
        $registrationForm?->localize();
        $registrationRecord = $user
            ? $event->registrations()->where('user_id', $user->id)->first()
            : null;

        return new CanonicalData([
            'event' => [
                ...$this->item($event),
                'description_html' => $this->embedRenderer->render($event->description ?? '', $event->church_id),
                'categories' => $event->categories->pluck('name')->values()->all(),
                'address' => $event->address,
                'price' => $event->price,
                'currency' => $event->church?->settings?->options['currency'] ?? 'BRL',
            ],
            'registration' => [
                'has_form' => $registrationForm !== null,
                'form_id' => $registrationForm?->id,
                'form_title' => $registrationForm?->title,
                'already_registered' => $registrationRecord !== null,
                'status' => $registrationRecord?->status,
                'can_access_private_area' => $registrationRecord?->status === 'confirmed',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function item(Event $event): array
    {
        $event->localize(relations: ['church']);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description
                ? (string) str(strip_tags(Str::markdown($event->description, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ])))->squish()
                : null,
            'cover_path' => $event->cover_url,
            'start_time' => $event->start_time?->toISOString(),
            'end_time' => $event->end_time?->toISOString(),
            'church' => $event->church ? [
                'id' => $event->church->id,
                'name' => $event->church->name,
                'slug' => $event->church->slug,
            ] : null,
        ];
    }
}
