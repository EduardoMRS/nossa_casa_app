<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Library;
use App\Services\Bible\BibleAccessResolver;
use App\Services\Bible\BibleApiClient;
use App\Support\ChurchDomainContext;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicLibraryController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly BibleAccessResolver $access,
        private readonly BibleApiClient $bible,
    ) {}

    public function index(): Response
    {
        $church = $this->context->church();
        $items = Library::query()
            ->when($church, fn ($query) => $query->where('church_id', $church->id))
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

        return Inertia::render('Library/Index', [
            'items' => $items,
            'bible' => $church instanceof Church ? $this->bibleCard($church) : null,
        ]);
    }

    public function bible(): Response
    {
        $church = $this->context->church();
        abort_unless($church instanceof Church, 404);
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

        return Inertia::render('Library/Bible', [
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
