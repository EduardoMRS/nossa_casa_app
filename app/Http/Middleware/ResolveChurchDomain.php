<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\ChurchDomainContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveChurchDomain
{
    public function __construct(private readonly ChurchDomainContext $context) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/internal/media/*')) {
            return $next($request);
        }

        $this->context->resolve($request);
        $church = $this->context->church();
        $user = $request->user();

        $isGlobalAdministrator = $user && in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);

        if ($user && ! $isGlobalAdministrator && $this->isDashboardRequest($request)) {
            $isUnassignedChurchDashboard = $church === null
                && $user->profile?->church_id !== null
                && blank($user->church?->domain);

            abort_unless(
                ($church && $user->profile?->church_id === $church->id) || $isUnassignedChurchDashboard,
                403,
                __('auth.church_membership_required'),
            );
        }

        if (! $church || ! $user || $isGlobalAdministrator) {
            return $next($request);
        }

        $userChurchId = $user->profile?->church_id;

        if ($userChurchId === $church->id) {
            return $next($request);
        }

        if ($this->isPublicChurchRoute($request)) {
            return $next($request);
        }

        return $this->restrictedResponse($request);
    }

    private function isDashboardRequest(Request $request): bool
    {
        return $request->is('dashboard', 'dashboard/*');
    }

    private function isPublicChurchRoute(Request $request): bool
    {
        $route = $request->route();

        if (! $route) {
            return false;
        }

        $routeName = $route->getName();
        $publicRouteNames = [
            'home',
            'sitemap',
            'robots',
            'communities.show',
            'events.index',
            'events.show',
            'events.register',
            'posts.public.index',
            'posts.public.show',
            'gallery.index',
            'gallery.download',
            'library.index',
            'library.bible',
            'library.show',
            'bible.offline',
            'bible.books',
            'bible.chapters',
            'bible.chapter',
            'live-streams.show',
            'secure-file',
            'pwa.manifest',
            'pwa.service-worker',
            'branding.logo',
            'branding.icon',
            'branding.favicon',
            'branding.favicon-svg',
            'branding.apple-touch-icon',
            'push-subscriptions.store',
            'push-subscriptions.destroy',
            'church.membership.switch',
            'church.membership.decline',
            'logout',
        ];

        if (in_array($routeName, $publicRouteNames, true)) {
            return true;
        }

        return in_array($route->getActionName(), [
            'App\\Http\\Controllers\\CommentController@store',
            'App\\Http\\Controllers\\CommentController@update',
            'App\\Http\\Controllers\\CommentController@destroy',
            'App\\Http\\Controllers\\ReactionController@store',
            'App\\Http\\Controllers\\ReactionController@destroy',
            'App\\Http\\Controllers\\EventController@checkin',
            'App\\Http\\Controllers\\FormResponseController@store',
            'App\\Http\\Controllers\\PrayerRequestController@store',
            'App\\Http\\Controllers\\ClassroomController@index',
            'App\\Http\\Controllers\\ClassroomController@checkIn',
            'App\\Http\\Controllers\\ClassroomController@checkOut',
        ], true);
    }

    private function restrictedResponse(Request $request): Response|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('auth.church_membership_required'),
            ], 403);
        }

        return redirect()->route('home');
    }
}
