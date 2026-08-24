<?php

namespace App\Queries;

use App\Data\CanonicalData;
use App\Models\Church;
use App\Models\Library;
use App\Services\Bible\BibleAccessResolver;
use App\Services\Bible\BibleApiClient;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final readonly class LibraryQuery
{
    public function __construct(
        private BibleAccessResolver $access,
        private BibleApiClient $bible,
    ) {}

    public function index(?Church $church): CanonicalData
    {
        $items = Library::query()
            ->when($church, fn (Builder $query): Builder => $query->where('church_id', $church->id))
            ->where('type', '!=', 'Versiculo do Dia')
            ->latest()
            ->paginate(18)
            ->through(function (Library $item): array {
                $item->localize();

                return [
                    'id' => $item->id,
                    'kind' => 'resource',
                    'title' => $item->title,
                    'description' => $item->description,
                    'type' => $item->type,
                    'file_url' => $item->file_url,
                    'href' => null,
                ];
            });

        return new CanonicalData([
            'items' => $items->toArray(),
            'bible' => $church ? $this->bibleCard($church) : null,
        ]);
    }

    public function bible(Church $church): CanonicalData
    {
        $access = $this->access->forChurch($church);

        try {
            $versions = collect($this->bible->versions())
                ->whereIn('id', $access['versions'])
                ->map(fn (array $version): array => [
                    ...$version,
                    'offline_url' => $version['offline_available']
                        ? route('bible.offline', ['version' => $version['id']], absolute: false)
                        : null,
                ])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);
            $versions = [];
        }

        return new CanonicalData([
            'versions' => $versions,
            'defaultVersion' => $access['default_version'],
        ]);
    }

    /** @return array{title: string, description: string, href: string, versions_count: int} */
    private function bibleCard(Church $church): array
    {
        $access = $this->access->forChurch($church);

        return [
            'title' => __('bible.library_title'),
            'description' => __('bible.library_description'),
            'href' => route('library.bible'),
            'versions_count' => count($access['versions']),
        ];
    }
}
