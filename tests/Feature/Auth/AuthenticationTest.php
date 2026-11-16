<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
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

    $response->assertRedirect('/');

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

    $response = $this->withHeader('X-Inertia', 'true')->post('http://visited-church.test/login', [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/events',
    ]);

    $response->assertStatus(409);
    $handoffUrl = $response->headers->get('X-Inertia-Location');

    expect($handoffUrl)->toStartWith('https://home-church.test/auth/handoff?token=');

    $this->withoutHeader('X-Inertia')
        ->get($handoffUrl)
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

test('unauthenticated user hitting dashboard on main domain is redirected to login and post-login lands on dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::SUPERADMIN]);

    $this->get('/dashboard')->assertRedirect('/login');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('unauthenticated user hitting dashboard on church domain lands on church dashboard post-login', function () {
    $church = Church::factory()->create(['domain' => 'my-church.test']);
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($user);

    $this->get('http://my-church.test/dashboard')->assertRedirect('http://my-church.test/login');

    $response = $this->post('http://my-church.test/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('church leader logging in on main domain is handed off to their church dashboard', function () {
    $church = Church::factory()->create(['domain' => 'leader-church.test']);
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($user);

    $response = $this->withHeader('X-Inertia', 'true')->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(409);
    $handoffUrl = $response->headers->get('X-Inertia-Location');
    expect($handoffUrl)->toStartWith('https://leader-church.test/auth/handoff?token=');

    $this->withoutHeader('X-Inertia')
        ->get($handoffUrl)
        ->assertRedirect('https://leader-church.test/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('system administrator logging in on church domain is handed off to portal dashboard on main domain', function () {
    Church::factory()->create(['domain' => 'any-church.test']);
    $user = User::factory()->create(['role' => UserRole::SYSTEM]);

    $response = $this->withHeader('X-Inertia', 'true')->post('http://any-church.test/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(409);
    $handoffUrl = $response->headers->get('X-Inertia-Location');
    expect($handoffUrl)->toContain('/auth/handoff?token=');

    $this->withoutHeader('X-Inertia')
        ->get($handoffUrl)
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('member hitting dashboard unauthenticated is redirected to home post-login avoiding 403', function () {
    $user = User::factory()->create(['role' => UserRole::MEMBER]);

    $this->get('/dashboard')->assertRedirect('/login');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('web visitor on main domain sees portal home even if affiliated with a church', function () {
    $church = Church::factory()->create(['domain' => 'church-member.test']);
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($user);

    $this->actingAs($user)
        ->get('/en')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Portal/Index')
        );
});

test('pwa visitor on main domain sees church home when church is resolved', function () {
    $church = Church::factory()->create(['domain' => 'church-member.test']);
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($user);

    $this->actingAs($user)
        ->withHeader('X-PWA-APP', '1')
        ->get('/en?church_id='.$church->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
        );
});
