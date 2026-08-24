<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\MobileAuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileLoginRequest;
use App\Http\Requests\Api\RefreshMobileSessionRequest;
use App\Models\MobileSession;
use App\Models\User;
use App\Services\MobileSessionService;
use App\Support\ChurchContext;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final class MobileAuthController extends Controller
{
    public function __construct(
        private MobileSessionService $mobileSessions,
        private TwoFactorAuthenticationProvider $twoFactorProvider,
        private ChurchContext $churchContext,
    ) {}

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->authenticatedUser($validated['email'], $validated['password']);

        if ($user->blocked_at !== null) {
            throw new MobileAuthenticationException('ACCOUNT_BLOCKED', 'auth.mobile.account_blocked', 403);
        }

        $this->ensureTwoFactorChallengePassed(
            $user,
            $validated['two_factor_code'] ?? null,
            $validated['recovery_code'] ?? null,
        );

        $credentials = $this->mobileSessions->create(
            $user,
            $validated['device_id'] ?? (string) Str::ulid(),
            $validated['device_name'],
            $validated['platform'],
            $validated['app_version'],
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['data' => $this->credentialsPayload($credentials)], 201);
    }

    public function refresh(RefreshMobileSessionRequest $request): JsonResponse
    {
        $credentials = $this->mobileSessions->refresh(
            $request->validated('refresh_token'),
            $request->validated('device_id'),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['data' => $this->credentialsPayload($credentials)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $mobileSession = MobileSession::query()
            ->where('current_access_token_id', $request->user()->currentAccessToken()?->getKey())
            ->first();
        $mobileSession?->devicePushTokens()->delete();
        $this->mobileSessions->revokeCurrentAccessToken(
            $request->user(),
            $request->user()->currentAccessToken(),
        );

        return response()->json(status: 204);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->devicePushTokens()->delete();
        $this->mobileSessions->revokeAllForUser($request->user(), 'logged_out_all');

        return response()->json(status: 204);
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentAccessTokenId = $request->user()->currentAccessToken()?->getKey();
        $sessions = $request->user()->mobileSessions()
            ->whereNull('revoked_at')
            ->latest('last_used_at')
            ->get()
            ->map(fn (MobileSession $session): array => $this->sessionPayload(
                $session,
                $session->current_access_token_id === $currentAccessTokenId,
            ));

        return response()->json(['data' => $sessions]);
    }

    public function destroySession(MobileSession $mobileSession): JsonResponse
    {
        Gate::authorize('delete', $mobileSession);

        $mobileSession->devicePushTokens()->delete();
        $this->mobileSessions->revoke($mobileSession);

        return response()->json(status: 204);
    }

    private function authenticatedUser(string $email, string $password): User
    {
        /** @var UserProvider $provider */
        $provider = Auth::guard('web')->getProvider();
        $credentials = ['email' => $email, 'password' => $password];
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            throw new MobileAuthenticationException('INVALID_CREDENTIALS', 'auth.failed');
        }

        return $user;
    }

    private function ensureTwoFactorChallengePassed(User $user, ?string $code, ?string $recoveryCode): void
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at === null) {
            return;
        }

        if (
            $code !== null
            && $this->twoFactorProvider->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            )
        ) {
            return;
        }

        if ($recoveryCode !== null) {
            $validRecoveryCode = collect($user->recoveryCodes())
                ->first(fn (string $candidate): bool => hash_equals($candidate, $recoveryCode));

            if ($validRecoveryCode !== null) {
                $user->replaceRecoveryCode($validRecoveryCode);

                return;
            }
        }

        $messageKey = $code === null && $recoveryCode === null
            ? 'auth.mobile.two_factor_required'
            : 'auth.mobile.two_factor_invalid';

        throw new MobileAuthenticationException(
            'TWO_FACTOR_REQUIRED',
            $messageKey,
            422,
            ['two_factor_code' => [__($messageKey)]],
        );
    }

    /**
     * @param  array{access_token: string, access_token_expires_at: \DateTimeInterface, refresh_token: string, session: MobileSession}  $credentials
     * @return array<string, mixed>
     */
    private function credentialsPayload(array $credentials): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $credentials['access_token'],
            'access_token_expires_at' => $credentials['access_token_expires_at']->format(DATE_ATOM),
            'refresh_token' => $credentials['refresh_token'],
            'refresh_token_expires_at' => $credentials['session']->expires_at->format(DATE_ATOM),
            'device_id' => $credentials['session']->device_id,
            'session' => $this->sessionPayload($credentials['session'], true),
            'user' => $this->userPayload($credentials['session']->user),
        ];
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $memberships = $user->churches()
            ->where('churches.status', 'active')
            ->orderBy('churches.name')
            ->get(['churches.id', 'churches.name', 'churches.slug', 'churches.domain']);
        $selectedChurchId = $this->churchContext->isResolved()
            ? $this->churchContext->churchId()
            : $user->profile?->church_id;

        $isGlobalAdministrator = in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);

        if (! $isGlobalAdministrator && ! $memberships->contains('id', $selectedChurchId)) {
            $selectedChurchId = $memberships->count() === 1 ? $memberships->first()?->id : null;
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'selected_church_id' => $selectedChurchId,
            'memberships' => $memberships->map(fn ($church): array => [
                'church_id' => $church->id,
                'name' => $church->name,
                'slug' => $church->slug,
                'domain' => $church->domain,
                'role' => $church->pivot->role ?? $user->role->value,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function sessionPayload(MobileSession $session, bool $current): array
    {
        return [
            'id' => $session->id,
            'device_id' => $session->device_id,
            'device_name' => $session->device_name,
            'platform' => $session->platform,
            'app_version' => $session->app_version,
            'last_used_at' => $session->last_used_at?->format(DATE_ATOM),
            'expires_at' => $session->expires_at->format(DATE_ATOM),
            'current' => $current,
        ];
    }
}
