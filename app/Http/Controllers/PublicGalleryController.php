<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Reaction;
use App\Support\ChurchDomainContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicGalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $churchId = app(ChurchDomainContext::class)->churchId();
        $media = Media::query()
            ->visible()
            ->where('gallery', true)
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
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
        ]);
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
        $user = $request->user();
        $role = $user?->role?->value ?? (string) $user?->role;

        return $role === 'system' || ($churchId !== null && $user?->profile?->church_id === $churchId);
    }
}
