<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Library;
use App\Models\Setting;
use App\Services\Bible\BibleAccessResolver;
use App\Services\Bible\BibleApiClient;
use App\Support\ChurchDomainContext;
use App\Traits\ManagesChurchCategories;
use App\Traits\UploadsMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LibraryVerseController extends Controller
{
    use ManagesChurchCategories;
    use UploadsMedia;

    public function __construct(
        private readonly BibleApiClient $bible,
        private readonly BibleAccessResolver $bibleAccess,
    ) {}

    public function index(Request $request): Response
    {
        $church = $this->currentChurch($request);
        $access = $this->bibleAccess->forChurch($church);

        try {
            $versions = $this->bible->versions($this->churchLocale($church));
            $bibleAvailable = true;
        } catch (Throwable $exception) {
            report($exception);
            $versions = [];
            $bibleAvailable = false;
        }

        return Inertia::render('Admin/LibraryVerse', [
            'verse' => $this->versePayload($church),
            'bible' => [
                ...$access,
                'catalog' => $versions,
                'available' => $bibleAvailable,
                'can_manage_community' => $this->bibleAccess->canManageCommunity($request->user(), $church),
            ],
            'categories' => $this->availableChurchCategories($church->id, CategoryType::LIBRARY->value),
            'libraries' => Library::query()
                ->where('church_id', $church->id)
                ->where('type', '!=', 'Versiculo do Dia')
                ->latest()
                ->get()
                ->map(fn (Library $library) => $this->libraryPayload($library))
                ->values(),
        ]);
    }

    public function storeLibrary(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:120'],
            'file_path' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
        ]);

        $churchId = $this->currentChurch($request)->id;

        $savedPath = $this->handleMediaUpload($request->file('file_path') ?? $validated['file_path'] ?? null, "church/{$churchId}/library");

        $library = Library::query()->create([
            ...$validated,
            'file_path' => $savedPath,
            'church_id' => $churchId,
        ]);

        $library->categories()->sync($this->syncChurchCategories($request, CategoryType::LIBRARY->value, $churchId));

        return back();
    }

    public function updateLibrary(Request $request, Library $library): RedirectResponse
    {
        $this->ensureChurchAccess($request, $library->church_id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:120'],
            'file_path' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
        ]);

        $savedPath = $this->handleMediaUpload($request->file('file_path') ?? $validated['file_path'] ?? null, "church/{$library->church_id}/library", $library->getRawOriginal('file_path'));

        $library->update([
            ...$validated,
            'file_path' => $savedPath,
        ]);

        if ($request->has('category_ids')) {
            $library->categories()->sync($this->syncChurchCategories($request, CategoryType::LIBRARY->value, $library->church_id));
        }

        return back();
    }

    public function destroyLibrary(Request $request, Library $library): RedirectResponse
    {
        $this->ensureChurchAccess($request, $library->church_id);
        $library->delete();

        return back();
    }

    public function updateBiblePreferences(Request $request): RedirectResponse
    {
        $church = $this->currentChurch($request);

        try {
            $catalogIds = collect($this->bible->versions())->pluck('id')->all();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['bible' => __('bible.service_unavailable')]);
        }

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['community', 'church'])],
            'versions' => ['required', 'array'],
            'versions.*' => ['required', 'string'],
            'default_version' => ['required', 'string', Rule::in($catalogIds)],
        ]);

        $versions = array_values(array_intersect(array_unique($validated['versions']), $catalogIds));

        if ($validated['scope'] === 'community') {
            abort_unless($church->community !== null && $this->bibleAccess->canManageCommunity($request->user(), $church), 403);

            abort_unless(in_array($validated['default_version'], $versions, true), 422, __('bible.default_version_invalid'));

            $church->community->update([
                'bible_versions' => $versions,
                'default_bible_version' => $validated['default_version'],
            ]);

            return back();
        }

        $communityVersions = $this->bibleAccess->forChurch($church)['community_versions'];

        $versions = array_values(array_intersect($versions, $communityVersions));

        abort_unless(in_array($validated['default_version'], $versions, true), 422, __('bible.default_version_invalid'));

        $setting = Setting::query()->firstOrCreate(['church_id' => $church->id], ['options' => []]);
        $options = $setting->options ?? [];
        data_set($options, 'bible.versions', $versions);
        data_set($options, 'bible.default_version', $validated['default_version']);
        $setting->update(['options' => $options]);

        return back();
    }

    public function updateVerse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string'],
            'book' => ['required', 'string', 'max:120'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verse' => ['required', 'integer', 'min:1'],
        ]);
        $church = $this->currentChurch($request);
        $access = $this->bibleAccess->forChurch($church);
        abort_unless(in_array($validated['version'], $access['versions'], true), 422, __('bible.version_not_available'));

        try {
            $books = $this->bible->books($validated['version']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['bible' => __('bible.service_unavailable')]);
        }

        abort_unless(collect($books)->contains('slug', $validated['book']), 422, __('bible.book_not_available'));

        try {
            $chapters = $this->bible->chapters($validated['version'], $validated['book']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['bible' => __('bible.service_unavailable')]);
        }

        abort_unless(in_array($validated['chapter'], $chapters, true), 422, __('bible.chapter_not_available'));

        try {
            $verse = $this->bible->verse(
                $validated['version'],
                $validated['book'],
                $validated['chapter'],
                $validated['verse'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['bible' => __('bible.service_unavailable')]);
        }
        $setting = Setting::query()->firstOrCreate(['church_id' => $church->id], ['options' => []]);
        $options = $setting->options ?? [];
        data_set($options, 'bible.daily_verse', [
            ...$validated,
            'book_name' => $verse['book'],
            'content' => $verse['text'],
        ]);
        $setting->update(['options' => $options]);

        return back();
    }

    /** @return array{book: string, book_name: string, chapter: int|string, verse: int|string, content: string, version: string, has_record: bool} */
    private function versePayload(Church $church): array
    {
        $verse = data_get($church->settings?->options, 'bible.daily_verse');
        $verse = is_array($verse) ? $verse : [];

        return [
            'book' => (string) ($verse['book'] ?? ''),
            'book_name' => (string) ($verse['book_name'] ?? ''),
            'chapter' => $verse['chapter'] ?? '',
            'verse' => $verse['verse'] ?? '',
            'content' => (string) ($verse['content'] ?? ''),
            'version' => (string) ($verse['version'] ?? ''),
            'has_record' => $verse !== [],
        ];
    }

    private function currentChurch(Request $request): Church
    {
        $domainContext = app(ChurchDomainContext::class);
        $church = $domainContext->church();
        abort_unless($church instanceof Church, 422, __('church.membership_library_manage_required'));

        return $church;
    }

    private function churchLocale(Church $church): string
    {
        return (string) (data_get($church->settings?->options, 'default_locale')
            ?: $church->community?->default_locale
            ?: config('app.locale'));
    }

    /** @return array<string, mixed> */
    private function libraryPayload(Library $library): array
    {
        return [
            'id' => $library->id,
            'title' => $library->title,
            'description' => $library->description,
            'type' => $library->type,
            'file_path' => $library->getRawOriginal('file_path'),
            'file_url' => $library->file_url,
            'preview_url' => $library->file_path ? genUrl($library->file_path) : null,
            'category_ids' => $library->categories()->pluck('categories.id')->all(),
        ];
    }
}
