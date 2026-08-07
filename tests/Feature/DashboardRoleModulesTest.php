<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

function createDashboardUser(string $role): User
{
    $community = Community::query()->create([
        'name' => 'Comunidade Dashboard '.fake()->unique()->word(),
        'description' => 'Comunidade para dashboard',
        'slug' => fake()->unique()->slug(),
    ]);

    $church = Church::query()->create([
        'name' => 'Igreja Dashboard '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'community_id' => $community->id,
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'role' => UserRole::from($role),
    ]);

    UserProfile::query()->create([
        'user_id' => $user->id,
        'church_id' => $church->id,
        'location_lang' => 'pt-BR',
    ]);

    return $user;
}

it('shows member-appropriate modules on dashboard', function () {
    $member = createDashboardUser('member');

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('modules', 5)
            ->where('modules.0.href', route('events.index'))
            ->where('modules.3.href', route('profile.edit'))
            ->where('modules.4.href', route('security.edit'))
        );
});

it('shows leader modules on dashboard', function () {
    $leader = createDashboardUser('leader');

    $this->actingAs($leader)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('modules', 5)
            ->where('modules.0.href', route('events.create'))
            ->where('modules.2.href', route('posts.index'))
        );
});

it('shows media modules on dashboard', function () {
    $media = createDashboardUser('media');

    $this->actingAs($media)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('modules', 5)
            ->where('modules.0.href', route('gallery.index'))
            ->where('modules.3.href', route('posts.index'))
        );
});

it('shows admin workspace modules on dashboard', function () {
    $admin = createDashboardUser('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('modules', 7)
            ->where('modules.0.href', route('dashboard'))
            ->where('modules.3.href', route('admin.branding.edit'))
            ->where('modules.6.href', route('admin.logsMetrics.index'))
        );
});
