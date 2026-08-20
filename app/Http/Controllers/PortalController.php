<?php

namespace App\Http\Controllers;

use App\Actions\Churches\BuildChurchRoadmap;
use App\Enums\ChurchStatus;
use App\Enums\LiveStreamStatus;
use App\Enums\UserRole;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\Event;
use App\Models\Highlight;
use App\Models\Media;
use App\Models\Post;
use App\Support\ChurchDomainContext;
use App\Support\GeoDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly GeoDistance $geoDistance,
        private readonly BuildChurchRoadmap $buildChurchRoadmap,
    ) {}

    public function index(Request $request): Response
    {
        return $this->context->isMainDomain()
            ? $this->platformHome($request)
            : $this->churchHome();
    }

    private function platformHome(Request $request): Response
    {
        $location = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
        $latitude = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $longitude = isset($location['longitude']) ? (float) $location['longitude'] : null;
        $communities = Community::query()
            ->with(['churches' => fn ($query) => $query
                ->where('status', ChurchStatus::ACTIVE)
                ->with(['address', 'settings'])
                ->withExists(['liveStreams as is_live' => fn ($liveStreams) => $liveStreams
                    ->publiclyVisible()
                    ->where('status', LiveStreamStatus::LIVE)
                    ->where('active_slot', 1)])
                ->orderBy('name')])
            ->withCount(['churches' => fn ($query) => $query->where('status', ChurchStatus::ACTIVE)])
            ->orderBy('name')
            ->get()
            ->map(function (Community $community) use ($latitude, $longitude): array {
                $churches = $community->churches->map(function ($church) use ($latitude, $longitude): array {
                    $address = $church->address->first();
                    $branding = $church->settings?->options['branding'] ?? [];
                    $churchLatitude = $address?->latitude ?? data_get($branding, 'latitude');
                    $churchLongitude = $address?->longitude ?? data_get($branding, 'longitude');
                    $distance = $latitude !== null && $longitude !== null && is_numeric($churchLatitude) && is_numeric($churchLongitude)
                        ? $this->geoDistance->between($latitude, $longitude, (float) $churchLatitude, (float) $churchLongitude)
                        : null;

                    return [
                        'id' => $church->id,
                        'name' => $church->name,
                        'slug' => $church->slug,
                        'domain' => $church->domain,
                        'url' => $church->domain ? $this->context->churchUrl($church) : null,
                        'is_live' => (bool) $church->is_live,
                        'distance_km' => $distance,
                    ];
                })->sortBy(fn (array $church): float => $church['distance_km'] ?? INF)->values();

                return [
                    'id' => $community->id,
                    'name' => $community->name,
                    'slug' => $community->slug,
                    'url' => route('communities.show', $community),
                    'description' => $community->description,
                    'churches_count' => $community->churches_count,
                    'distance_km' => $churches->pluck('distance_km')->filter(fn ($distance) => $distance !== null)->min(),
                    'churches' => $churches,
                ];
            });
        $communities = $communities
            ->sort(fn (array $first, array $second): int => $this->compareByDistanceAndName($first, $second))
            ->values();
        $nearbyCommunities = $latitude !== null && $longitude !== null
            ? $communities->whereNotNull('distance_km')->sortBy('distance_km')->take(4)->values()
            : collect();

        $reviewableRequests = collect();
        $myRequests = collect();

        if ($request->user()) {
            $user = $request->user();
            $communityId = $user->profile?->community_id ?? $user->church?->community_id;
            $ownedCommunityIds = Community::query()->where('owner_id', $user->id)->pluck('id');
            $reviewableRequests = ChurchRegistrationRequest::query()
                ->where('status', 'pending')
                ->when($user->role !== UserRole::SYSTEM, function ($query) use ($user, $communityId, $ownedCommunityIds): void {
                    $allowedCommunityIds = $ownedCommunityIds;

                    if ($communityId && in_array($user->role, [UserRole::CHURCH_LEADER, UserRole::SUPERADMIN], true)) {
                        $allowedCommunityIds = $allowedCommunityIds->push($communityId);
                    }

                    $query->whereIn('community_id', $allowedCommunityIds->unique());
                })
                ->with(['community:id,name', 'requester:id,first_name,last_name,email'])
                ->latest()
                ->get();
            $myRequests = ChurchRegistrationRequest::query()
                ->where('requester_id', $user->id)
                ->with('community:id,name')
                ->latest()
                ->get();
        }

        return Inertia::render('Portal/Index', [
            'communities' => $communities,
            'nearbyCommunities' => $nearbyCommunities,
            'locationApplied' => $latitude !== null && $longitude !== null,
            'canOnboard' => (bool) $request->user(),
            'userCommunityId' => $request->user()?->profile?->community_id,
            'userChurchUrl' => $request->user()?->church?->domain
                ? $this->context->churchUrl($request->user()->church)
                : null,
            'mainDomain' => $this->context->mainHost(),
            'reviewableRequests' => $reviewableRequests,
            'myRequests' => $myRequests,
        ]);
    }

    private function churchHome(): Response
    {
        $church = $this->context->church();
        abort_unless($church, 404);
        $churchId = $church->id;
        $user = request()->user();
        $role = $user?->role?->value ?? (string) $user?->role;
        $canPreviewPosts = $user !== null
            && ($role === 'system' || $user->profile?->church_id === $churchId)
            && in_array($role, ['leader', 'media', 'church_leader', 'superadmin', 'system'], true);
        $eventHighlightOrder = Highlight::query()
            ->where('church_id', $churchId)
            ->where('highlightable_type', Event::class)
            ->orderBy('order')
            ->pluck('highlightable_id')
            ->flip();
        $featuredEvents = Event::query()
            ->where('church_id', $churchId)
            ->where('start_time', '>=', now()->startOfDay())
            ->with('church:id,name,slug,domain')
            ->orderBy('start_time')
            ->limit(30)
            ->get()
            ->sortBy(fn (Event $event) => $eventHighlightOrder->get($event->id, PHP_INT_MAX))
            ->take(3)
            ->map(function (Event $event) {
                $event->localize(relations: ['church']);

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'excerpt' => Str::limit(strip_tags((string) $event->description), 120),
                    'cover_path' => $event->cover_url,
                    'start_time' => $event->start_time,
                    'church' => $event->church,
                ];
            });
        $postHighlightOrder = Highlight::query()
            ->where('church_id', $churchId)
            ->where('highlightable_type', Post::class)
            ->orderBy('order')
            ->pluck('highlightable_id')
            ->flip();
        $latestPosts = Post::query()
            ->where('church_id', $churchId)
            ->when(
                $canPreviewPosts,
                fn ($query) => $query->visible(),
                fn ($query) => $query->published(),
            )
            ->latest('published_at')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->sortBy(fn (Post $post) => $postHighlightOrder->get($post->id, PHP_INT_MAX))
            ->take(3)
            ->map(function (Post $post) {
                $post->localize();

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => Str::limit((string) preg_replace('/\s+/', ' ', trim(strip_tags(Str::markdown((string) $post->content, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ])))), 120),
                    'published_at' => $post->published_at,
                ];
            });
        $calendarEvents = Event::query()
            ->where('church_id', $churchId)
            ->whereBetween('start_time', [now()->subYear()->startOfMonth(), now()->addYear()->endOfMonth()])
            ->orderBy('start_time')
            ->get(['id', 'title', 'slug', 'start_time', 'end_time'])
            ->map(function (Event $event): array {
                $event->localize();

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                ];
            });
        $latestRecordings = Media::query()
            ->where('church_id', $churchId)
            ->visible()
            ->where('gallery', true)
            ->whereHas('recording')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'title' => $media->title,
                'url' => $media->url,
                'mimetype' => $media->mimetype,
                'created_at' => $media->created_at,
            ]);

        return Inertia::render('Home', [
            'stats' => [
                'events' => Event::query()->where('church_id', $churchId)->where('start_time', '>=', now()->startOfDay())->count(),
                'gallery' => Media::query()->where('church_id', $churchId)->visible()->count(),
                'posts' => Post::query()
                    ->where('church_id', $churchId)
                    ->when(
                        $canPreviewPosts,
                        fn ($query) => $query->visible(),
                        fn ($query) => $query->published(),
                    )
                    ->count(),
            ],
            'featuredEvents' => $featuredEvents,
            'latestPosts' => $latestPosts,
            'latestRecordings' => $latestRecordings,
            'calendarEvents' => $calendarEvents,
            'roadmap' => $this->buildChurchRoadmap->handle($church, $canPreviewPosts),
            'communityUrl' => $church->community
                ? rtrim((string) config('app.url'), '/').'/communities/'.$church->community->slug
                : rtrim((string) config('app.url'), '/'),
        ]);
    }

    /**
     * @param  array{name: string, distance_km: float|null}  $first
     * @param  array{name: string, distance_km: float|null}  $second
     */
    private function compareByDistanceAndName(array $first, array $second): int
    {
        if (($first['distance_km'] === null) !== ($second['distance_km'] === null)) {
            return $first['distance_km'] === null ? 1 : -1;
        }

        if ($first['distance_km'] !== null && $first['distance_km'] !== $second['distance_km']) {
            return $first['distance_km'] <=> $second['distance_km'];
        }

        return strnatcasecmp($first['name'], $second['name']);
    }
}
