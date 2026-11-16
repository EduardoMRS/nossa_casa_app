<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use App\Support\ChurchAccess;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class ChurchAwareLoginResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    public function __construct(private readonly ChurchDomainContext $context) {}

    public function toResponse($request): Response
    {
        $user = $request->user();
        $domainChurch = $this->context->church();
        $userChurch = $user?->church;

        $hasPortalDashboardAccess = $user && in_array($user->role, [
            UserRole::SUPERADMIN,
            UserRole::SYSTEM,
        ], true);

        $hasChurchDashboardAccess = $user && in_array($user->role, [
            UserRole::CHURCH_LEADER,
            UserRole::LEADER,
            UserRole::TREASURER,
            UserRole::MEDIA,
        ], true);

        $isPwa = $request->header('X-PWA-APP') === '1' || $request->boolean('pwa');

        $safeRedirect = $this->safeRedirectPath($request);

        if ($isPwa) {
            $redirectQuery = [];

            if (is_string($safeRedirect)) {
                parse_str((string) parse_url($safeRedirect, PHP_URL_QUERY), $redirectQuery);
            }

            if (($redirectQuery['pwa_membership'] ?? null) === '1' && $safeRedirect !== null) {
                return $this->response($request, $safeRedirect);
            }

            $query = array_filter([
                'pwa' => '1',
                'church_id' => $userChurch?->id,
            ]);

            return $this->response(
                $request,
                route('home', ['locale' => app()->getLocale()], absolute: false).'?'.http_build_query($query),
            );
        }

        // Determine destination target path
        if ($safeRedirect !== null) {
            $isDashboardPath = str_starts_with($safeRedirect, '/dashboard') || $safeRedirect === '/dashboard';
            if ($isDashboardPath && ! $hasPortalDashboardAccess && ! $hasChurchDashboardAccess) {
                $targetPath = route('home', absolute: false);
            } else {
                $targetPath = $safeRedirect;
            }
        } else {
            $targetPath = ($hasPortalDashboardAccess || $hasChurchDashboardAccess)
                ? route('dashboard', absolute: false)
                : route('home', absolute: false);
        }

        // 1. Users with Portal Dashboard access (SUPERADMIN / SYSTEM)
        if ($hasPortalDashboardAccess) {
            if ($this->context->isMainDomain()) {
                return $this->response($request, $targetPath);
            }

            return $this->handoffResponse($request, $user, null, $targetPath);
        }

        // 2. Users with Church Dashboard access (CHURCH_LEADER, LEADER, TREASURER, MEDIA)
        if ($hasChurchDashboardAccess) {
            $isCrossChurch = $domainChurch && ! app(ChurchAccess::class)->canReachChurch($user, $domainChurch->id);
            $targetChurch = $isCrossChurch ? $userChurch : ($domainChurch ?? $userChurch);

            // If logging in from an unrelated church, destination on own church should be dashboard unless specific dashboard path
            if ($isCrossChurch && ! (str_starts_with($targetPath, '/dashboard') || $targetPath === '/dashboard')) {
                $targetPath = route('dashboard', absolute: false);
            }

            if ($targetChurch?->domain) {
                $currentHost = ChurchDomainContext::normalizeDomain($request->getHost());
                $targetHost = ChurchDomainContext::normalizeDomain($targetChurch->domain);

                if ($currentHost !== $targetHost) {
                    return $this->handoffResponse($request, $user, $targetChurch, $targetPath);
                }
            }

            return $this->response($request, $targetPath);
        }

        // 3. Regular users (MEMBER / GUEST)
        if ($user && $userChurch?->domain) {
            $currentHost = ChurchDomainContext::normalizeDomain($request->getHost());
            $targetHost = ChurchDomainContext::normalizeDomain($userChurch->domain);

            if ($currentHost !== $targetHost) {
                return $this->handoffResponse($request, $user, $userChurch, $targetPath);
            }
        }

        return $this->response($request, $targetPath);
    }

    private function handoffResponse(Request $request, User $user, ?Church $church = null, ?string $redirect = null): Response
    {
        $token = Str::random(64);
        Cache::put('church-auth-handoff:'.$token, [
            'user_id' => $user->id,
            'church_id' => $church?->id,
            'redirect' => $redirect,
            'user_agent' => hash('sha256', (string) $request->userAgent()),
        ], now()->addMinutes(2));

        $handoffUrl = $church
            ? $this->context->churchUrl($church, 'auth/handoff?token='.urlencode($token))
            : rtrim((string) config('app.url'), '/').'/auth/handoff?token='.urlencode($token);

        return $this->response(
            $request,
            $handoffUrl,
            external: true,
        );
    }

    private function safeRedirectPath(Request $request): ?string
    {
        $redirect = $request->input('redirect');

        if (is_string($redirect) && $redirect !== '') {
            $request->session()->forget('auth.login_redirect');
            $request->session()->forget('url.intended');
        } else {
            $redirect = $request->session()->pull('auth.login_redirect');

            if (blank($redirect) && $request->hasSession() && $request->session()->has('url.intended')) {
                $intended = (string) $request->session()->pull('url.intended');
                $parts = parse_url($intended);
                if ($parts !== false && ! empty($parts['path'])) {
                    $redirect = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
                }
            }
        }

        if (! is_string($redirect) || ! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return null;
        }

        $parts = parse_url($redirect);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        return $redirect;
    }

    /** @param array<string, mixed> $extra */
    private function response(Request $request, string $url, array $extra = [], bool $external = false): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => $url, ...$extra]);
        }

        if ($external) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }
}
