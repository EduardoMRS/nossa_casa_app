<?php

namespace App\Queries;

use App\Data\CanonicalData;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class PostQuery
{
    /** @param array{search?: string, category?: string, date_from?: string, date_to?: string, sort?: string} $filters */
    public function index(array $filters, ?string $churchId): CanonicalData
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = $filters['sort'] ?? 'latest';
        $postsQuery = $this->publishedPosts($churchId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $categoryId): Builder => $query->whereHas(
                'categories',
                fn (Builder $categoryQuery): Builder => $categoryQuery->whereKey($categoryId),
            ))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('published_at', '<=', $date));

        $sort === 'popular'
            ? $postsQuery->orderByDesc('views_count')->latest('published_at')
            : $postsQuery->latest('published_at');

        $posts = $postsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->card($post));
        $mostViewed = $this->publishedPosts($churchId)
            ->orderByDesc('views_count')
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Post $post): array => $this->card($post))
            ->all();

        return new CanonicalData([
            'posts' => $posts->toArray(),
            'categories' => Category::query()
                ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
                ->where('type', CategoryType::POST->value)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->each->localize()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->all(),
            'filters' => [
                'search' => $search,
                'category' => $filters['category'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'sort' => $sort,
            ],
            'mostViewed' => $mostViewed,
        ]);
    }

    public function show(string $slug, ?string $churchId, ?User $user): CanonicalData
    {
        $post = $this->viewablePosts($churchId, $user)->where('slug', $slug)->firstOrFail();
        $isPublished = $post->published_at?->isPast()
            && ($post->expires_at === null || $post->expires_at->isFuture());

        if ($isPublished) {
            $post->increment('views_count');
        }

        $post->localize(relations: ['church', 'categories']);
        $comments = $post->comments()
            ->with(['user:id,first_name,last_name', 'reactions.user:id,first_name,last_name'])
            ->latest()
            ->get();
        $reactions = $post->reactions()->with('user:id,first_name,last_name')->latest()->get();
        $categoryIds = $post->categories->modelKeys();
        $relatedPosts = $this->publishedPosts($post->church_id)
            ->whereKeyNot($post->id)
            ->when($categoryIds !== [], fn (Builder $query): Builder => $query->whereHas(
                'categories',
                fn (Builder $categoryQuery): Builder => $categoryQuery->whereKey($categoryIds),
            ))
            ->latest('published_at')->limit(5)->get()
            ->map(fn (Post $relatedPost): array => $this->card($relatedPost))->all();
        $latestPosts = $this->publishedPosts($post->church_id)
            ->whereKeyNot($post->id)->latest('published_at')->limit(5)->get()
            ->map(fn (Post $latestPost): array => $this->card($latestPost))->all();

        return new CanonicalData([
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'contentHtml' => Str::markdown((string) $post->content, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]),
                'published_at' => $post->published_at?->toISOString(),
                'church' => $this->personOrChurch($post->church),
                'author' => $this->personOrChurch($post->author),
                'category' => $post->categories->pluck('name')->join(', '),
                'cover_url' => $post->medias->first()?->url,
                'views_count' => $post->views_count,
                'metrics' => [
                    'comments_count' => $comments->count(),
                    'reactions_count' => $reactions->count(),
                ],
            ],
            'comments' => $comments->map(fn (Comment $comment): array => $this->comment($comment))->all(),
            'reactions' => $reactions->map(fn (Reaction $reaction): array => $this->reaction($reaction))->all(),
            'canInteract' => $user !== null && $post->church_id !== null,
            'relatedPosts' => $relatedPosts,
            'latestPosts' => $latestPosts,
        ]);
    }

    private function publishedPosts(?string $churchId): Builder
    {
        return Post::query()
            ->published()
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->with(['church:id,name,slug', 'author:id,first_name,last_name', 'categories:id,name', 'medias'])
            ->withCount(['comments', 'reactions']);
    }

    private function viewablePosts(?string $churchId, ?User $user): Builder
    {
        $role = $user?->role?->value ?? (string) $user?->role;
        $canPreview = $user !== null
            && ($role === 'system' || $user->churches()->whereKey($churchId)->exists())
            && in_array($role, ['leader', 'media', 'church_leader', 'superadmin', 'system'], true);

        return Post::query()
            ->when($canPreview, fn (Builder $query): Builder => $query->visible(), fn (Builder $query): Builder => $query->published())
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->with(['church:id,name,slug', 'author:id,first_name,last_name', 'categories:id,name', 'medias'])
            ->withCount(['comments', 'reactions']);
    }

    /** @return array<string, mixed> */
    private function card(Post $post): array
    {
        $post->localize(relations: ['church', 'categories']);

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => (string) str(strip_tags(Str::markdown((string) $post->content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])))->squish()->limit(220),
            'published_at' => $post->published_at?->toISOString(),
            'church' => $this->personOrChurch($post->church),
            'author' => $this->personOrChurch($post->author),
            'categories' => $post->categories->pluck('name')->values()->all(),
            'cover_url' => $post->medias->first()?->url,
            'comments_count' => (int) $post->comments_count,
            'reactions_count' => (int) $post->reactions_count,
            'views_count' => (int) $post->views_count,
        ];
    }

    /** @return array<string, mixed> */
    private function comment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'is_pinned' => (bool) $comment->is_pinned,
            'created_at' => $comment->created_at?->toISOString(),
            'user' => $this->personOrChurch($comment->user),
            'reactions' => $comment->reactions->map(fn (Reaction $reaction): array => $this->reaction($reaction))->all(),
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
            'user' => $this->personOrChurch($reaction->user),
        ];
    }

    /** @return array<string, mixed>|null */
    private function personOrChurch(mixed $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return array_filter([
            'id' => $model->id,
            'name' => $model->name ?? null,
            'slug' => $model->slug ?? null,
            'first_name' => $model->first_name ?? null,
            'last_name' => $model->last_name ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
