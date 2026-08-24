<?php

use App\Enums\ChurchStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use App\Support\ChurchContext;
use App\Support\ChurchDomainContext;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'church.context:required'])
        ->get('/api/test-required-church-context', fn () => response()->json([
            'church_id' => app(ChurchContext::class)->churchId(),
        ]));
});

test('web and api resolve the same scoped church context service', function () {
    expect(app(ChurchContext::class))->toBe(app(ChurchDomainContext::class));
});

test('api church header selects an active membership and exposes it from auth me', function () {
    $church = Church::factory()->create();
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($user);

    $this->withToken($user->createToken('context-test')->plainTextToken)
        ->withHeader('X-Church-ID', $church->id)
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.selected_church_id', $church->id)
        ->assertJsonPath('data.memberships.0.church_id', $church->id);
});

test('api church header never grants access without membership', function () {
    $church = Church::factory()->create();
    $user = User::factory()->create(['role' => UserRole::MEMBER]);

    $this->withToken($user->createToken('context-test')->plainTextToken)
        ->withHeader('X-Church-ID', $church->id)
        ->getJson('/api/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});

test('inactive and malformed church contexts are rejected', function () {
    $inactiveChurch = Church::factory()->create(['status' => ChurchStatus::INACTIVE]);
    $user = User::factory()->create(['role' => UserRole::SYSTEM]);
    $token = $user->createToken('context-test')->plainTextToken;

    $this->withToken($token)
        ->withHeader('X-Church-ID', 'invalid')
        ->getJson('/api/auth/me')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'CHURCH_CONTEXT_INVALID');

    $this->withToken($token)
        ->withHeader('X-Church-ID', $inactiveChurch->id)
        ->getJson('/api/auth/me')
        ->assertNotFound()
        ->assertJsonPath('code', 'CHURCH_NOT_FOUND');
});

test('public api context accepts an active church without authentication', function () {
    $church = Church::factory()->create();

    $this->withHeader('X-Church-ID', $church->id)
        ->getJson('/api/church')
        ->assertSuccessful();
});

test('global administrators may select any active church', function (UserRole $role) {
    $church = Church::factory()->create();
    $user = User::factory()->create(['role' => $role]);

    $this->withToken($user->createToken('context-test')->plainTextToken)
        ->withHeader('X-Church-ID', $church->id)
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.selected_church_id', $church->id);
})->with([
    'superadmin' => UserRole::SUPERADMIN,
    'system' => UserRole::SYSTEM,
]);

test('a user with no memberships has no automatic church', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('context-test')->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.selected_church_id', null)
        ->assertJsonCount(0, 'data.memberships');
});

test('a user with one membership selects it automatically', function () {
    $church = Church::factory()->create();
    $user = User::factory()->create();
    $user->churches()->attach($church, ['role' => UserRole::MEMBER->value]);

    $this->withToken($user->createToken('context-test')->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.selected_church_id', $church->id);
});

test('a user with several memberships must provide a selection when none is preferred', function () {
    $churches = Church::factory()->count(2)->create();
    $user = User::factory()->create();

    $user->churches()->attach($churches->pluck('id')->mapWithKeys(
        fn (string $churchId): array => [$churchId => ['role' => UserRole::MEMBER->value]],
    )->all());

    $this->withToken($user->createToken('context-test')->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.selected_church_id', null)
        ->assertJsonCount(2, 'data.memberships');

    $this->withToken($user->createToken('required-context')->plainTextToken)
        ->getJson('/api/test-required-church-context')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'CHURCH_CONTEXT_REQUIRED');
});
