<?php

namespace App\Services;

use App\Exceptions\MobileAuthenticationException;
use App\Models\MobileSession;
use App\Models\MobileSessionRefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class MobileSessionService
{
    /**
     * @return array{access_token: string, access_token_expires_at: \DateTimeInterface, refresh_token: string, session: MobileSession}
     */
    public function create(
        User $user,
        string $deviceId,
        string $deviceName,
        string $platform,
        string $appVersion,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $credentials = DB::transaction(function () use ($user, $deviceId, $deviceName, $platform, $appVersion, $ipAddress, $userAgent): array {
            $user->mobileSessions()
                ->where('device_id', $deviceId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(fn (MobileSession $session) => $this->revokeLockedSession($session));

            $session = $user->mobileSessions()->create([
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'platform' => $platform,
                'app_version' => $appVersion,
                'token_family' => (string) Str::ulid(),
                'active_slot' => 1,
                'expires_at' => now()->addDays($this->refreshExpirationDays()),
                'last_used_at' => now(),
                'last_ip' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $this->issueCredentials($session);
        }, attempts: 3);

        $this->audit('created', $credentials['session']);

        return $credentials;
    }

    /**
     * @return array{access_token: string, access_token_expires_at: \DateTimeInterface, refresh_token: string, session: MobileSession}
     */
    public function refresh(
        string $plainRefreshToken,
        string $deviceId,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $outcome = DB::transaction(function () use ($plainRefreshToken, $deviceId, $ipAddress, $userAgent): array {
            $refreshToken = MobileSessionRefreshToken::query()
                ->where('token_hash', $this->hashRefreshToken($plainRefreshToken))
                ->lockForUpdate()
                ->first();

            if (! $refreshToken) {
                return ['error' => 'invalid'];
            }

            $session = MobileSession::query()
                ->with('user')
                ->lockForUpdate()
                ->find($refreshToken->mobile_session_id);

            if (! $session) {
                return ['error' => 'invalid'];
            }

            if ($refreshToken->used_at !== null) {
                $this->revokeFamilyLocked($session->token_family);

                return ['error' => 'reused', 'session' => $session];
            }

            if (
                $session->revoked_at !== null
                || $session->expires_at->isPast()
                || ! hash_equals($session->device_id, $deviceId)
                || $session->user->blocked_at !== null
            ) {
                $this->revokeLockedSession($session);

                return ['error' => 'invalid', 'session' => $session];
            }

            $refreshToken->update(['used_at' => now()]);
            $session->currentAccessToken?->delete();
            $session->forceFill([
                'current_access_token_id' => null,
                'last_used_at' => now(),
                'last_ip' => $ipAddress,
                'user_agent' => $userAgent,
            ])->save();

            return ['credentials' => $this->issueCredentials($session->fresh(['user']))];
        }, attempts: 3);

        if (($outcome['error'] ?? null) === 'reused') {
            $this->audit('refresh_reused', $outcome['session']);

            throw new MobileAuthenticationException(
                'REFRESH_TOKEN_REUSED',
                'auth.mobile.refresh_reused',
            );
        }

        if (isset($outcome['error'])) {
            if (isset($outcome['session'])) {
                $this->audit('refresh_rejected', $outcome['session']);
            }

            throw new MobileAuthenticationException(
                'INVALID_REFRESH_TOKEN',
                'auth.mobile.invalid_refresh_token',
            );
        }

        $credentials = $outcome['credentials'];
        $this->audit('refreshed', $credentials['session']);

        return $credentials;
    }

    public function revoke(MobileSession $session, string $event = 'revoked'): void
    {
        DB::transaction(function () use ($session): void {
            $lockedSession = MobileSession::query()->lockForUpdate()->find($session->id);

            if ($lockedSession) {
                $this->revokeLockedSession($lockedSession);
            }
        }, attempts: 3);

        $this->audit($event, $session);
    }

    public function revokeAllForUser(User $user, string $event = 'revoked_all'): void
    {
        $sessions = DB::transaction(function () use ($user) {
            $sessions = $user->mobileSessions()
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            $sessions->each(fn (MobileSession $session) => $this->revokeLockedSession($session));

            return $sessions;
        }, attempts: 3);

        $sessions->each(fn (MobileSession $session) => $this->audit($event, $session));
    }

    public function revokeCurrentAccessToken(User $user, mixed $accessToken): void
    {
        if (! $accessToken instanceof PersonalAccessToken) {
            return;
        }

        $session = $user->mobileSessions()
            ->where('current_access_token_id', $accessToken->getKey())
            ->first();

        if ($session) {
            $this->revoke($session, 'logged_out');

            return;
        }

        $accessToken->delete();
    }

    /**
     * @return array{access_token: string, access_token_expires_at: \DateTimeInterface, refresh_token: string, session: MobileSession}
     */
    private function issueCredentials(MobileSession $session): array
    {
        $plainRefreshToken = Str::random(96);
        $accessTokenExpiresAt = now()->addMinutes($this->accessExpirationMinutes());
        $accessToken = $session->user->createToken(
            'mobile:'.$session->id,
            ['mobile'],
            $accessTokenExpiresAt,
        );

        $session->refreshTokens()->create([
            'token_hash' => $this->hashRefreshToken($plainRefreshToken),
        ]);
        $session->forceFill([
            'current_access_token_id' => $accessToken->accessToken->getKey(),
        ])->save();

        return [
            'access_token' => $accessToken->plainTextToken,
            'access_token_expires_at' => $accessTokenExpiresAt,
            'refresh_token' => $plainRefreshToken,
            'session' => $session->fresh(['user']),
        ];
    }

    private function revokeFamilyLocked(string $tokenFamily): void
    {
        MobileSession::query()
            ->where('token_family', $tokenFamily)
            ->lockForUpdate()
            ->get()
            ->each(fn (MobileSession $session) => $this->revokeLockedSession($session));
    }

    private function revokeLockedSession(MobileSession $session): void
    {
        $session->currentAccessToken?->delete();
        $session->forceFill([
            'current_access_token_id' => null,
            'active_slot' => null,
            'revoked_at' => $session->revoked_at ?? now(),
        ])->save();
    }

    private function hashRefreshToken(string $plainRefreshToken): string
    {
        return hash('sha256', $plainRefreshToken);
    }

    private function accessExpirationMinutes(): int
    {
        return max(1, (int) config('mobile.access_token_expiration', 60));
    }

    private function refreshExpirationDays(): int
    {
        return max(1, (int) config('mobile.refresh_token_expiration_days', 30));
    }

    private function audit(string $event, MobileSession $session): void
    {
        Log::info('mobile_session.audit', [
            'event' => $event,
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'device_id' => $session->device_id,
            'platform' => $session->platform,
            'ip' => $session->last_ip,
        ]);
    }
}
