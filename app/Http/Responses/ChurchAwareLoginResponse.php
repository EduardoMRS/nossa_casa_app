<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class ChurchAwareLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    public function __construct(private readonly ChurchDomainContext $context) {}

    public function toResponse($request): Response
    {
        $user = $request->user();
        $domainChurch = $this->context->church();
        $userChurch = $user?->church;

        $isGlobalAdministrator = $user && in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);

        if ($domainChurch && $user && ! $isGlobalAdministrator && $userChurch?->id !== $domainChurch->id) {
            return $userChurch?->domain
                ? $this->handoffResponse($request, $user, $userChurch)
                : $this->response($request, route('home', absolute: false));
        }

        if ($this->context->isMainDomain() && $user && $userChurch?->domain && ! $isGlobalAdministrator) {
            return $this->handoffResponse($request, $user, $userChurch);
        }

        return $this->response(
            $request,
            $this->safeRedirectPath($request) ?? route('home', absolute: false),
        );
    }

    private function handoffResponse(Request $request, User $user, Church $church): Response
    {
        $token = Str::random(64);
        Cache::put('church-auth-handoff:'.$token, [
            'user_id' => $user->id,
            'church_id' => $church->id,
            'user_agent' => hash('sha256', (string) $request->userAgent()),
        ], now()->addMinutes(2));

        return $this->response(
            $request,
            $this->context->churchUrl($church, 'auth/handoff?token='.urlencode($token)),
            external: true,
        );
    }

    private function safeRedirectPath(Request $request): ?string
    {
        $redirect = $request->input('redirect');

        if (is_string($redirect) && $redirect !== '') {
            $request->session()->forget('auth.login_redirect');
        } else {
            $redirect = $request->session()->pull('auth.login_redirect');
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
