<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $response = $this->get('http://platform.test/dashboard');
    $response->assertRedirect(route('login'));
});

test('members cannot visit the dashboard directly', function () {
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    Church::factory()->create()->assignMember($user);

    $this->actingAs($user)->get('http://platform.test/dashboard')->assertForbidden();
});

test('roles above member can visit the dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::LEADER]);
    Church::factory()->create()->assignMember($user);

    $this->actingAs($user)->get('http://platform.test/dashboard')->assertOk();
});

test('superadmin can access any church dashboard and manage its settings', function () {
    $church = Church::factory()->create(['domain' => 'visited.test']);
    $superadmin = User::factory()->create(['role' => UserRole::SUPERADMIN]);

    $this->actingAs($superadmin)
        ->get('http://visited.test/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('churchContext.church.id', $church->id)
            ->where('permissions.accessDashboard', true)
            ->where('permissions.manageBranding', true));

    $this->get('http://visited.test/dashboard/configuracoes-church')->assertSuccessful();
});

test('global dashboard on the portal hides church settings without church context', function () {
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);

    $this->actingAs($system)
        ->get('http://platform.test/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('churchContext.church', null)
            ->where('permissions.accessDashboard', true)
            ->where('permissions.manageBranding', false)
            ->where('modules', fn ($modules): bool => collect($modules)
                ->doesntContain(fn (array $module): bool => str_contains($module['href'], 'configuracoes-church'))));

    $this->get('http://platform.test/dashboard/configuracoes-church')->assertForbidden();
});
