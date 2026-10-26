<?php

namespace App\Queries;

use App\Data\CanonicalData;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Builder;

final class MediaQuery
{
    public function index(?string $churchId, string $view, bool $canInteract, ?string $focusMediaId = null): CanonicalData
    {
        $view = $view === 'transmissions' ? 'transmissions' : 'gallery';
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
                'church:id,domain',
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
            ->through(fn (Media $item): array => $this->item($item));
        $categories = Category::query()
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->where('type', CategoryType::MEDIA->value)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type'])
            ->each->localize()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'type' => $category->type,
            ])->all();

        $focusedMedia = null;

        if ($focusMediaId !== null && $focusMediaId !== '') {
            $focused = Media::query()
                ->visible()
                ->where('gallery', true)
                ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
                ->with([
                    'church:id,domain',
                    'uploader:id,first_name,last_name',
                    'categories:id,name,slug',
                    'comments' => fn ($query) => $query
                        ->with(['user:id,first_name,last_name', 'reactions.user:id,first_name,last_name'])
                        ->latest(),
                    'reactions.user:id,first_name,last_name',
                ])
                ->find($focusMediaId);

            if ($focused !== null) {
                $focusedMedia = $this->item($focused);
            }
        }

        return new CanonicalData([
            'media' => $media->toArray(),
            'categories' => $categories,
            'canInteract' => $canInteract,
            'view' => $view,
            'focusedMedia' => $focusedMedia,
        ]);
    }

    /** @return array<string, mixed> */
    private function item(Media $media): array
    {
        $media->localize(relations: ['categories']);
        // Retorna a URL otimizada de preview caso disponível, com fallback para o original
        $previewUrl = $media->preview_url;

        return [
            'id' => $media->id,
            'url' => $previewUrl,
            'original_url' => $media->url,
            'download_url' => route('gallery.download', $media),
            'title' => filled($media->title) ? $media->title : null,
            'description' => $media->description,
            'mimetype' => $media->mimetype,
            'size' => $media->size,
            'uploader' => $this->user($media->uploader),
            'categories' => $media->categories->pluck('name')->values()->all(),
            'created_at' => $media->created_at?->toISOString(),
            'comments' => $media->comments->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toISOString(),
                'user_details' => $this->user($comment->user),
                'reactions' => $comment->reactions->map(fn (Reaction $reaction): array => $this->reaction($reaction))->all(),
            ])->all(),
            'reactions' => $media->reactions->map(fn (Reaction $reaction): array => $this->reaction($reaction))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function reaction(Reaction $reaction): array
    {
        return [
            'id' => $reaction->id,
            'user_id' => $reaction->user_id,
            'content' => $reaction->content,
            'type' => $reaction->type,
            'user_details' => $this->user($reaction->user),
        ];
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ] : null;
    }
}
