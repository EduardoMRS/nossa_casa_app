<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Reaction;
use App\Support\ChurchDomainContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicGalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $churchId = app(ChurchDomainContext::class)->churchId();
        $view = $request->string('view')->toString() === 'transmissions'
            ? 'transmissions'
            : 'gallery';
        $media = Media::query()
            ->visible()
            ->where('gallery', true)
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->when(
                $view === 'transmissions',
                fn (Builder $query): Builder => $query->whereHas('recording'),
                fn (Builder $query): Builder => $query->whereDoesntHave('recording'),
            )
            ->with([
                'uploader:id,first_name,last_name',
                'categories:id,name,slug',
                'comments' => fn ($query) => $query
                    ->with(['user:id,first_name,last_name', 'reactions.user:id,first_name,last_name'])
                    ->latest(),
                'reactions.user:id,first_name,last_name',
            ])
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Media $item): array => $this->mediaItem($item));

        return Inertia::render('Gallery/Index', [
            'media' => $media,
            'categories' => Category::query()
                ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
                ->where('type', CategoryType::MEDIA->value)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type'])
                ->each->localize(),
            'canInteract' => $this->canInteract($request, $churchId),
            'view' => $view,
        ]);
    }

    public function download(Media $media): StreamedResponse
    {
        $churchId = app(ChurchDomainContext::class)->churchId();

        abort_unless($media->status === MediaStatus::APPROVED && $media->gallery, 404);
        abort_if($churchId && $media->church_id !== $churchId, 404);

        return Storage::disk($media->disk ?: (string) config('media.disk'))
            ->download($media->file_path, basename($media->file_path));
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaItem(Media $media): array
    {
        $media->localize(relations: ['categories']);

        return [
            'id' => $media->id,
            'url' => $media->url,
            'download_url' => route('gallery.download', $media),
            'title' => $media->title ?: $this->fallbackTitle($media->file_path),
            'description' => $media->description,
            'mimetype' => $media->mimetype,
            'size' => $media->size,
            'uploader' => $media->uploader,
            'categories' => $media->categories->pluck('name')->values(),
            'created_at' => $media->created_at,
            'comments' => $media->comments->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at,
                'user_details' => $comment->user,
                'reactions' => $comment->reactions->map(fn (Reaction $reaction): array => $this->reactionItem($reaction)),
            ]),
            'reactions' => $media->reactions->map(fn (Reaction $reaction): array => $this->reactionItem($reaction)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reactionItem(Reaction $reaction): array
    {
        return [
            'id' => $reaction->id,
            'user_id' => $reaction->user_id,
            'content' => $reaction->content,
            'type' => $reaction->type,
            'user_details' => $reaction->user,
        ];
    }

    private function fallbackTitle(string $filePath): string
    {
        $path = parse_url($filePath, PHP_URL_PATH) ?: $filePath;

        return (string) Str::of(pathinfo($path, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->headline();
    }

    private function canInteract(Request $request, ?string $churchId): bool
    {
        return $request->user() !== null && $churchId !== null;
    }
}
