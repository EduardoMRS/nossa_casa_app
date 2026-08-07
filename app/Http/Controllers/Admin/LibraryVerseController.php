<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Models\Vercicle;
use App\Traits\ManagesChurchCategories;
use App\Traits\UploadsMedia;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryVerseController extends Controller
{
    use ManagesChurchCategories;
    use UploadsMedia;

    public function index(): Response
    {
        $churchId = request()->user()?->church?->id;

        return Inertia::render('Admin/LibraryVerse', [
            'verse' => $this->versePayload($churchId),
            'categories' => $this->availableChurchCategories($churchId, CategoryType::LIBRARY->value),
            'libraries' => Library::query()
                ->where('church_id', $churchId)
                ->where('type', '!=', 'Versiculo do Dia')
                ->latest()
                ->get()
                ->map(fn (Library $library) => $this->libraryPayload($library))
                ->values(),
        ]);
    }

    public function storeLibrary(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:120'],
            'file_path' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
        ]);

        $churchId = $request->user()->church?->id;
        abort_unless($churchId !== null, 422, 'A church membership is required to manage the library.');

        $savedPath = $this->handleMediaUpload($request->file('file_path') ?? $validated['file_path'] ?? null, "church/{$churchId}/library");

        $library = Library::query()->create([
            ...$validated,
            'file_path' => $savedPath,
            'church_id' => $churchId,
        ]);

        $library->categories()->sync($this->syncChurchCategories($request, CategoryType::LIBRARY->value, $churchId));

        return back();
    }

    public function updateLibrary(Request $request, Library $library)
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

        $savedPath = $this->handleMediaUpload($request->file('file_path') ?? $validated['file_path'] ?? null, "church/{$library->church_id}/library", $library->file_path);

        $library->update([
            ...$validated,
            'file_path' => $savedPath,
        ]);

        if ($request->has('category_ids')) {
            $library->categories()->sync($this->syncChurchCategories($request, CategoryType::LIBRARY->value, $library->church_id));
        }

        return back();
    }

    public function destroyLibrary(Request $request, Library $library)
    {
        $this->ensureChurchAccess($request, $library->church_id);
        $library->delete();

        return back();
    }

    public function updateVerse(Request $request)
    {
        $validated = $request->validate([
            'book' => ['required', 'string', 'max:120'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verse' => ['required', 'integer', 'min:1'],
            'content' => ['required', 'string'],
            'version' => ['required', 'string', 'max:120'],
        ]);

        $churchId = $request->user()->church?->id;
        abort_unless($churchId !== null, 422, 'A church membership is required to manage the verse of the day.');

        $library = Library::query()->firstOrCreate(
            ['church_id' => $churchId, 'type' => 'Versiculo do Dia'],
            [
                'title' => 'Versículo do Dia',
                'description' => 'Registro do versiculo devocional usado na home.',
                'file_path' => null,
            ],
        );

        $verse = Vercicle::query()->where('library_id', $library->id)->latest()->first();

        if ($verse === null) {
            $verse = Vercicle::query()->create([
                ...$validated,
                'library_id' => $library->id,
            ]);
        } else {
            $verse->update($validated);
        }

        return back();
    }

    private function versePayload(?string $churchId): array
    {
        $verse = Vercicle::query()
            ->whereHas('library', fn ($query) => $query->where('church_id', $churchId))
            ->latest()
            ->first();

        return [
            'book' => $verse?->book ?? '',
            'chapter' => $verse?->chapter ?? '',
            'verse' => $verse?->verse ?? '',
            'content' => $verse?->content ?? '',
            'version' => $verse?->version ?? '',
            'has_record' => $verse !== null,
        ];
    }

    private function libraryPayload(Library $library): array
    {
        return [
            'id' => $library->id,
            'title' => $library->title,
            'description' => $library->description,
            'type' => $library->type,
            'file_path' => $library->file_path,
            'preview_url' => $library->file_path ? genUrl($library->file_path) : null,
            'category_ids' => $library->categories()->pluck('categories.id')->all(),
        ];
    }
}
