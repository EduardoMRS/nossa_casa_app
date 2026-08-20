<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Post;
use App\Support\ChurchDomainContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicPostController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:26'],
            'date_from' => ['nullable', 'date'],
            'date_to' => [
                'nullable',
                'date',
                Rule::when($request->filled('date_from'), ['after_or_equal:date_from']),
            ],
            'sort' => ['nullable', 'in:latest,popular'],
        ]);
        $churchId = app(ChurchDomainContext::class)->churchId();
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

        if ($sort === 'popular') {
            $postsQuery->orderByDesc('views_count')->latest('published_at');
        } else {
            $postsQuery->latest('published_at');
        }

        $posts = $postsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

        $mostViewed = $this->publishedPosts($churchId)
            ->orderByDesc('views_count')
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Post $post): array => $this->postCard($post));

        return Inertia::render('Posts/PublicIndex', [
            'posts' => $posts,
            'categories' => $this->postCategories($churchId),
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

    public function show(Request $request, string $slug): Response
    {
        $churchId = app(ChurchDomainContext::class)->churchId();
        $post = $this->viewablePosts($request, $churchId)
            ->where('slug', $slug)
            ->firstOrFail();

        $isPublished = $post->published_at?->isPast()
            && ($post->expires_at === null || $post->expires_at->isFuture());

        if ($isPublished) {
            $post->increment('views_count');
        }
        $post->localize(relations: ['church', 'categories']);

        $comments = $post->comments()
            ->with([
                'user:id,first_name,last_name',
                'reactions.user:id,first_name,last_name',
            ])
            ->latest()
            ->get();
        $reactions = $post->reactions()
            ->with('user:id,first_name,last_name')
            ->latest()
            ->get();
        $categoryIds = $post->categories->modelKeys();
        $relatedPosts = $this->publishedPosts($post->church_id)
            ->whereKeyNot($post->id)
            ->when($categoryIds !== [], fn (Builder $query): Builder => $query->whereHas(
                'categories',
                fn (Builder $categoryQuery): Builder => $categoryQuery->whereKey($categoryIds),
            ))
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Post $relatedPost): array => $this->postCard($relatedPost));
        $latestPosts = $this->publishedPosts($post->church_id)
            ->whereKeyNot($post->id)
            ->latest('published_at')
            ->limit(5)
            ->get()
            ->map(fn (Post $latestPost): array => $this->postCard($latestPost));

        return Inertia::render('Posts/PublicShow', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'contentHtml' => Str::markdown((string) $post->content, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]),
                'published_at' => $post->published_at,
                'church' => $post->church,
                'author' => $post->author,
                'category' => $post->categories->pluck('name')->join(', '),
                'cover_url' => $post->medias->first()?->url,
                'views_count' => $post->views_count,
                'metrics' => [
                    'comments_count' => $comments->count(),
                    'reactions_count' => $reactions->count(),
                ],
            ],
            'comments' => $comments,
            'reactions' => $reactions,
            'canInteract' => $this->canInteract($request, $post->church_id),
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

    private function viewablePosts(Request $request, ?string $churchId): Builder
    {
        $user = $request->user();
        $role = $user?->role?->value ?? (string) $user?->role;
        $canPreview = $user !== null
            && ($role === 'system' || $user->profile?->church_id === $churchId)
            && in_array($role, ['leader', 'media', 'church_leader', 'superadmin', 'system'], true);

        return Post::query()
            ->when(
                $canPreview,
                fn (Builder $query): Builder => $query->visible(),
                fn (Builder $query): Builder => $query->published(),
            )
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->with(['church:id,name,slug', 'author:id,first_name,last_name', 'categories:id,name', 'medias'])
            ->withCount(['comments', 'reactions']);
    }

    /**
     * @return array<string, mixed>
     */
    private function postCard(Post $post): array
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
            'published_at' => $post->published_at,
            'church' => $post->church,
            'author' => $post->author,
            'categories' => $post->categories->pluck('name')->values(),
            'cover_url' => $post->medias->first()?->url,
            'comments_count' => $post->comments_count,
            'reactions_count' => $post->reactions_count,
            'views_count' => $post->views_count,
        ];
    }

    private function postCategories(?string $churchId): Collection
    {
        return Category::query()
            ->when($churchId, fn (Builder $query): Builder => $query->where('church_id', $churchId))
            ->where('type', CategoryType::POST->value)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->each->localize();
    }

    private function canInteract(Request $request, ?string $churchId): bool
    {
        return $request->user() !== null && $churchId !== null;
    }
}
