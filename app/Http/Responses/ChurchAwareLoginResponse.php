<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
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

        if ($domainChurch && $user && $user->role !== UserRole::SYSTEM && $userChurch?->id !== $domainChurch->id) {
            return $this->response($request, route('home', absolute: false));
        }

        if ($this->context->isMainDomain() && $userChurch?->domain) {
            $token = Str::random(64);
            Cache::put('church-auth-handoff:'.$token, [
                'user_id' => $user->id,
                'church_id' => $userChurch->id,
                'user_agent' => hash('sha256', (string) $request->userAgent()),
            ], now()->addMinutes(2));

            return $this->response(
                $request,
                $this->context->churchUrl($userChurch, 'auth/handoff?token='.urlencode($token)),
                external: true,
            );
        }

        $canAccessDashboard = $user !== null && in_array($user->role, [
            UserRole::LEADER,
            UserRole::MEDIA,
            UserRole::CHURCH_LEADER,
            UserRole::SUPERADMIN,
            UserRole::SYSTEM,
        ], true);

        return $this->response(
            $request,
            route($canAccessDashboard ? 'dashboard' : 'home', absolute: false),
        );
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
