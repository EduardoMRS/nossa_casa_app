<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('dashboard users return to the public page where login started', function () {
    $church = Church::factory()->create(['domain' => 'current-church.test']);
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($user);

    $this->post('http://current-church.test/login', [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/events?view=upcoming',
    ])->assertRedirect('/events?view=upcoming');

    $this->assertAuthenticatedAs($user);
});

test('login from another church hands dashboard users back to their own church', function () {
    $homeChurch = Church::factory()->create(['domain' => 'home-church.test']);
    Church::factory()->create(['domain' => 'visited-church.test']);
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $homeChurch->assignMember($user);

    $response = $this->post('http://visited-church.test/login', [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/events',
    ]);

    $response->assertStatus(409);
    $handoffUrl = $response->headers->get('X-Inertia-Location');

    expect($handoffUrl)->toStartWith('https://home-church.test/auth/handoff?token=');

    $this->get($handoffUrl)
        ->assertRedirect('https://home-church.test/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('login ignores unsafe external return URLs', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => 'https://malicious.example/redirect',
    ])->assertRedirect(route('home', absolute: false));
});
