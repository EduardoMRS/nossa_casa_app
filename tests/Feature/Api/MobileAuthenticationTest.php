<?php

use App\Models\MobileSession;
use App\Models\MobileSessionRefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

function mobileLoginPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'member@example.test',
        'password' => 'password',
        'device_id' => (string) Str::ulid(),
        'device_name' => 'Test device',
        'platform' => 'android',
        'app_version' => '1.0.0',
    ], $overrides);
}

test('mobile login issues hashed refresh credentials and a usable sanctum token', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);

    $response = $this->postJson('/api/auth/login', mobileLoginPayload())
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'access_token_expires_at',
                'refresh_token',
                'refresh_token_expires_at',
                'device_id',
                'session',
                'user',
            ],
        ]);

    $refreshToken = $response->json('data.refresh_token');
    $storedRefreshToken = MobileSessionRefreshToken::query()->firstOrFail();

    expect($storedRefreshToken->token_hash)
        ->toBe(hash('sha256', $refreshToken))
        ->not->toBe($refreshToken)
        ->and(MobileSession::query()->firstOrFail()->current_access_token_id)->not->toBeNull();

    $this->withToken($response->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);
});

test('mobile login generates a secure device id when the client has none', function () {
    User::factory()->create(['email' => 'member@example.test']);

    $payload = mobileLoginPayload();
    unset($payload['device_id']);

    $deviceId = $this->postJson('/api/auth/login', $payload)
        ->assertCreated()
        ->json('data.device_id');

    expect(Str::isUlid($deviceId))->toBeTrue();
});

test('mobile auth uses the common validation and credential error contracts', function () {
    User::factory()->create(['email' => 'member@example.test']);

    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors(['email', 'password', 'device_name', 'platform', 'app_version']);

    $this->postJson('/api/auth/login', mobileLoginPayload(['password' => 'invalid']))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

test('two factor users cannot bypass the challenge and may consume a recovery code', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'member@example.test']);

    $this->postJson('/api/auth/login', mobileLoginPayload())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'TWO_FACTOR_REQUIRED');

    $this->postJson('/api/auth/login', mobileLoginPayload([
        'recovery_code' => 'recovery-code-1',
    ]))->assertCreated();

    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

test('refresh rotates both credentials and reused refresh tokens revoke the family', function () {
    User::factory()->create(['email' => 'member@example.test']);
    $deviceId = (string) Str::ulid();
    $login = $this->postJson('/api/auth/login', mobileLoginPayload(['device_id' => $deviceId]))
        ->assertCreated();

    $refresh = $this->postJson('/api/auth/refresh', [
        'refresh_token' => $login->json('data.refresh_token'),
        'device_id' => $deviceId,
    ])->assertSuccessful();

    expect($refresh->json('data.refresh_token'))->not->toBe($login->json('data.refresh_token'))
        ->and($refresh->json('data.access_token'))->not->toBe($login->json('data.access_token'));

    $this->withToken($login->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->postJson('/api/auth/refresh', [
        'refresh_token' => $login->json('data.refresh_token'),
        'device_id' => $deviceId,
    ])->assertUnauthorized()
        ->assertJsonPath('code', 'REFRESH_TOKEN_REUSED');

    $this->withToken($refresh->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    expect(MobileSession::query()->firstOrFail()->revoked_at)->not->toBeNull();
});

test('a refresh token is bound to its generated device id', function () {
    User::factory()->create(['email' => 'member@example.test']);
    $login = $this->postJson('/api/auth/login', mobileLoginPayload())->assertCreated();

    $this->postJson('/api/auth/refresh', [
        'refresh_token' => $login->json('data.refresh_token'),
        'device_id' => (string) Str::ulid(),
    ])->assertUnauthorized()
        ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
});

test('users can list and revoke their own device sessions only', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);
    $first = $this->postJson('/api/auth/login', mobileLoginPayload())->assertCreated();
    $second = $this->postJson('/api/auth/login', mobileLoginPayload([
        'device_id' => (string) Str::ulid(),
        'device_name' => 'Second device',
    ]))->assertCreated();

    $sessions = $this->withToken($first->json('data.access_token'))
        ->getJson('/api/auth/sessions')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');

    $secondSessionId = collect($sessions->json('data'))->firstWhere('device_name', 'Second device')['id'];

    $this->withToken($first->json('data.access_token'))
        ->deleteJson('/api/auth/sessions/'.$secondSessionId)
        ->assertNoContent();

    Auth::forgetGuards();

    $this->withToken($second->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $otherSession = MobileSession::factory()->create();

    $this->withToken($first->json('data.access_token'))
        ->deleteJson('/api/auth/sessions/'.$otherSession->id)
        ->assertForbidden();

    expect($otherSession->fresh()->revoked_at)->toBeNull()
        ->and($user->mobileSessions()->whereNull('revoked_at')->count())->toBe(1);
});

test('logout and logout all revoke the expected mobile access tokens', function () {
    User::factory()->create(['email' => 'member@example.test']);
    $first = $this->postJson('/api/auth/login', mobileLoginPayload())->assertCreated();
    $second = $this->postJson('/api/auth/login', mobileLoginPayload([
        'device_id' => (string) Str::ulid(),
    ]))->assertCreated();

    $this->withToken($first->json('data.access_token'))
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    Auth::forgetGuards();

    $this->withToken($first->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->withToken($second->json('data.access_token'))
        ->postJson('/api/auth/logout-all')
        ->assertNoContent();

    expect(MobileSession::query()->whereNull('revoked_at')->count())->toBe(0);
});

test('password changes and account blocking revoke all mobile sessions', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);
    $login = $this->postJson('/api/auth/login', mobileLoginPayload())->assertCreated();

    $user->forceFill(['password' => Hash::make('new-password')])->save();

    $this->withToken($login->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $relogin = $this->postJson('/api/auth/login', mobileLoginPayload([
        'password' => 'new-password',
        'device_id' => (string) Str::ulid(),
    ]))->assertCreated();

    $user->forceFill(['blocked_at' => now()])->save();

    $this->withToken($relogin->json('data.access_token'))
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->postJson('/api/auth/login', mobileLoginPayload([
        'password' => 'new-password',
    ]))->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_BLOCKED');
});

test('mobile login has a dedicated rate limit with the stable error code', function () {
    User::factory()->create(['email' => 'member@example.test']);
    $payload = mobileLoginPayload(['password' => 'invalid']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', $payload)
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'RATE_LIMITED');
});

test('mobile session audit context never contains issued credentials', function () {
    Log::spy();
    User::factory()->create(['email' => 'member@example.test']);

    $response = $this->postJson('/api/auth/login', mobileLoginPayload())->assertCreated();
    $accessToken = $response->json('data.access_token');
    $refreshToken = $response->json('data.refresh_token');

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($accessToken, $refreshToken): bool {
        $serializedContext = json_encode($context, JSON_THROW_ON_ERROR);

        return $message === 'mobile_session.audit'
            && ! str_contains($serializedContext, $accessToken)
            && ! str_contains($serializedContext, $refreshToken);
    })->atLeast()->once();
});
