<?php

namespace App\Services;

use App\Enums\ContributionType;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\Media;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds the contextual dashboard payload for a church, varying by user role:
 * recent activity, access map (from the anonymous public access log),
 * most-liked content and quick shortcuts.
 */
class DashboardInsights
{
    public function __construct(private PublicAccessLogger $accessLogger) {}

    /**
     * @return array{
     *     recent_activity: array<int, array{type: string, title: string, at: string|null}>,
     *     most_liked: array<int, array{type: string, title: string, likes: int}>,
     *     access_map: array<int, array{country_code: string|null, country: string|null, region: string|null, latitude: float|null, longitude: float|null, visits: int}>,
     *     recent_prayers: array<int, array{content: string, is_anonymous: bool, at: string|null}>,
     *     treasury: array{month: string, tithe_total: string, offering_total: string, total: string}|null,
     *     shortcuts: array<int, array{label_key: string, href: string}>
     * }
     */
    public function forChurch(?Church $church, User $user): array
    {
        // Without a resolved church every query below would be unscoped and
        // leak data from all churches, so only shortcuts are returned.
        if ($church === null) {
            return [
                'recent_activity' => [],
                'most_liked' => [],
                'access_map' => [],
                'recent_prayers' => [],
                'treasury' => null,
                'shortcuts' => $this->shortcuts($user->role),
            ];
        }

        $churchId = $church->id;

        return [
            'recent_activity' => $this->recentActivity($churchId),
            'most_liked' => $this->mostLiked($churchId),
            'access_map' => $this->canSeeAccessMap($user) ? $this->accessMap($churchId) : [],
            'recent_prayers' => $this->canSeePrayers($user) ? $this->recentPrayers($churchId) : [],
            'treasury' => $this->canSeeTreasury($user) ? $this->treasurySummary($church) : null,
            'shortcuts' => $this->shortcuts($user->role),
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, at: string|null}>
     */
    private function recentActivity(?string $churchId): array
    {
        $posts = Post::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'created_at'])
            ->map(fn (Post $post): array => [
                'type' => 'post',
                'title' => (string) $post->title,
                'at' => $post->created_at?->toISOString(),
            ]);

        $events = Event::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'created_at'])
            ->map(fn (Event $event): array => [
                'type' => 'event',
                'title' => (string) $event->title,
                'at' => $event->created_at?->toISOString(),
            ]);

        $media = Media::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'original_filename', 'created_at'])
            ->map(fn (Media $item): array => [
                'type' => 'media',
                'title' => (string) ($item->title ?: $item->original_filename),
                'at' => $item->created_at?->toISOString(),
            ]);

        return $posts->concat($events)->concat($media)
            ->sortByDesc('at')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Most liked church content (posts and media), ranked by reaction count.
     *
     * @return array<int, array{type: string, title: string, likes: int}>
     */
    private function mostLiked(?string $churchId): array
    {
        $postIds = Post::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->pluck('id');
        $mediaIds = Media::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->pluck('id');

        $counts = Reaction::query()
            ->selectRaw('reactionable_type, reactionable_id, count(*) as likes')
            ->where(function ($query) use ($postIds, $mediaIds): void {
                $query->where(fn ($inner) => $inner
                    ->where('reactionable_type', Post::class)
                    ->whereIn('reactionable_id', $postIds))
                    ->orWhere(fn ($inner) => $inner
                        ->where('reactionable_type', Media::class)
                        ->whereIn('reactionable_id', $mediaIds));
            })
            ->groupBy('reactionable_type', 'reactionable_id')
            ->orderByDesc('likes')
            ->limit(5)
            ->get();

        return $counts->map(function ($row): array {
            $model = $row->reactionable_type::query()->find($row->reactionable_id);
            $title = $model?->title ?? $model?->original_filename ?? '';

            return [
                'type' => $row->reactionable_type === Post::class ? 'post' : 'media',
                'title' => (string) $title,
                'likes' => (int) $row->likes,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accessMap(?string $churchId): array
    {
        return $this->accessLogger
            ->visitorMap(Carbon::now()->subDays(30), $churchId)
            ->take(10)
            ->values()
            ->all();
    }

    private function canSeeAccessMap(User $user): bool
    {
        return in_array($user->role, [
            UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM,
        ], true);
    }

    /**
     * Latest prayer requests for pastoral follow-up.
     *
     * @return array<int, array{content: string, is_anonymous: bool, at: string|null}>
     */
    private function recentPrayers(?string $churchId): array
    {
        return PrayerRequest::query()
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->with('user:id,first_name,last_name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PrayerRequest $prayer): array => [
                'content' => Str::limit((string) $prayer->content, 160),
                'is_anonymous' => $prayer->user === null,
                'at' => $prayer->created_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * Current month tithe/offering totals for the treasury widget.
     *
     * @return array{month: string, tithe_total: string, offering_total: string, total: string}
     */
    private function treasurySummary(?Church $church): array
    {
        $month = Carbon::now()->startOfMonth();
        $query = Contribution::query()
            ->when($church, fn ($inner) => $inner->where('church_id', $church->id))
            ->whereBetween('contributed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);

        $tithe = (clone $query)->where('type', ContributionType::TITHE->value)->sum('amount');
        $offering = (clone $query)->where('type', ContributionType::OFFERING->value)->sum('amount');

        return [
            'month' => $month->format('Y-m'),
            'tithe_total' => number_format((float) $tithe, 2, '.', ''),
            'offering_total' => number_format((float) $offering, 2, '.', ''),
            'total' => number_format((float) $tithe + (float) $offering, 2, '.', ''),
        ];
    }

    /**
     * Mirrors the prayer requests admin module, restricted to church leadership.
     */
    private function canSeePrayers(User $user): bool
    {
        return in_array($user->role, [
            UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM,
        ], true);
    }

    private function canSeeTreasury(User $user): bool
    {
        return in_array($user->role, [
            UserRole::TREASURER, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM,
        ], true);
    }

    /**
     * @return array<int, array{label_key: string, href: string}>
     */
    private function shortcuts(?UserRole $role): array
    {
        $createPost = ['label_key' => 'dashboard.shortcuts.new_post', 'href' => route('posts.create')];
        $createEvent = ['label_key' => 'dashboard.shortcuts.new_event', 'href' => route('events.create')];
        $openGallery = ['label_key' => 'dashboard.shortcuts.gallery', 'href' => route('gallery.index')];
        $openTreasury = ['label_key' => 'dashboard.shortcuts.treasury', 'href' => route('admin.treasury.settings')];

        return match ($role) {
            UserRole::MEDIA => [$createPost, $openGallery],
            UserRole::LEADER => [$createEvent, $createPost],
            UserRole::TREASURER => [$openTreasury],
            UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM => [$createPost, $createEvent, $openGallery, $openTreasury],
            default => [$openGallery],
        };
    }
}
