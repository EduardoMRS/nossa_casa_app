<?php

namespace App\Http\Controllers;

use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\Event;
use App\Models\Highlight;
use App\Models\Media;
use App\Models\Post;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    public function __construct(private readonly ChurchDomainContext $context) {}

    public function index(Request $request): Response
    {
        return $this->context->isMainDomain()
            ? $this->platformHome($request)
            : $this->churchHome();
    }

    private function platformHome(Request $request): Response
    {
        $communities = Community::query()
            ->with(['churches' => fn ($query) => $query
                ->where('status', ChurchStatus::ACTIVE)
                ->orderBy('name')])
            ->withCount(['churches' => fn ($query) => $query->where('status', ChurchStatus::ACTIVE)])
            ->orderBy('name')
            ->get()
            ->map(fn (Community $community) => [
                'id' => $community->id,
                'name' => $community->name,
                'slug' => $community->slug,
                'description' => $community->description,
                'churches_count' => $community->churches_count,
                'churches' => $community->churches->map(fn ($church) => [
                    'id' => $church->id,
                    'name' => $church->name,
                    'slug' => $church->slug,
                    'domain' => $church->domain,
                    'url' => $church->domain ? $this->context->churchUrl($church) : null,
                ]),
            ]);

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

                    if ($communityId && in_array($user->role, [UserRole::ADMIN, UserRole::SUPERADMIN], true)) {
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
            'canOnboard' => (bool) $request->user(),
            'userCommunityId' => $request->user()?->profile?->community_id,
            'userChurchUrl' => $request->user()?->church?->domain
                ? $this->context->churchUrl($request->user()->church)
                : null,
            'reviewableRequests' => $reviewableRequests,
            'myRequests' => $myRequests,
        ]);
    }

    private function churchHome(): Response
    {
        $churchId = $this->context->churchId();
        $eventHighlightOrder = Highlight::query()
            ->where('highlightable_type', Event::class)
            ->orderBy('order')
            ->pluck('highlightable_id')
            ->flip();
        $featuredEvents = Event::query()
            ->where('church_id', $churchId)
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
                    'cover_path' => $event->cover_path,
                    'start_time' => $event->start_time,
                    'church' => $event->church,
                ];
            });
        $postHighlightOrder = Highlight::query()
            ->where('highlightable_type', Post::class)
            ->orderBy('order')
            ->pluck('highlightable_id')
            ->flip();
        $latestPosts = Post::query()
            ->where('church_id', $churchId)
            ->visible()
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

        return Inertia::render('Home', [
            'stats' => [
                'events' => Event::query()->where('church_id', $churchId)->count(),
                'gallery' => Media::query()->where('church_id', $churchId)->visible()->count(),
                'posts' => Post::query()->where('church_id', $churchId)->count(),
            ],
            'featuredEvents' => $featuredEvents,
            'latestPosts' => $latestPosts,
        ]);
    }
}
