<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\ChurchDomainContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (! $church || ! $user || $user->role === UserRole::SYSTEM) {
            return $next($request);
        }

        $userChurchId = $user->profile?->church_id;

        if ($userChurchId === $church->id) {
            $request->session()->forget('church_membership_pending');

            return $next($request);
        }

        if ($request->session()->get('church_membership_pending') === $church->id) {
            if ($this->isProtectedRoute($request)) {
                return redirect()->route('home');
            }

            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->loggedOutResponse($request);
    }

    private function isProtectedRoute(Request $request): bool
    {
        $route = $request->route();

        if (! $route) {
            return false;
        }

        if (in_array($route->getName(), ['church.membership.switch', 'church.membership.decline', 'logout'], true)) {
            return false;
        }

        return in_array('auth', $route->gatherMiddleware(), true);
    }

    private function loggedOutResponse(Request $request): Response|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('auth.church_domain_session_ended'),
            ], 401);
        }

        return redirect()->route('login')->with('status', __('auth.church_domain_session_ended'));
    }
}
