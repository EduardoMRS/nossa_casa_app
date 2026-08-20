<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function managementUser(UserRole $role = UserRole::CHURCH_LEADER): User
{
    $church = Church::query()->create([
        'name' => fake()->company(),
        'slug' => fake()->unique()->slug(),
    ]);
    $user = User::factory()->create(['role' => $role]);
    $church->assignMember($user);

    return $user;
}

it('opens the church scoped administrative events list', function () {
    $admin = managementUser();
    $event = Event::query()->create([
        'church_id' => $admin->profile->church_id,
        'author_id' => $admin->id,
        'title' => 'Administrative event',
        'slug' => 'administrative-event',
        'description' => 'Visible in management regardless of date.',
        'start_time' => now()->subMonth(),
        'end_time' => now()->subMonth()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.events.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Events')
            ->where('events.data.0.id', $event->id));
});

it('opens event creation and editing from dedicated management urls', function () {
    $admin = managementUser();
    $event = Event::query()->create([
        'church_id' => $admin->profile->church_id,
        'author_id' => $admin->id,
        'title' => 'Editable event',
        'slug' => 'editable-event',
        'description' => 'Event content',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('events.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Form')
            ->where('returnUrl', route('admin.events.index')));

    $this->actingAs($admin)
        ->get(route('events.edit', ['event' => $event->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Form')
            ->where('event.id', $event->id));
});

it('scopes multicongregation management to the administrators community', function () {
    $community = Community::query()->create([
        'name' => 'Local community',
        'slug' => 'local-community',
        'description' => 'Local churches',
    ]);
    $otherCommunity = Community::query()->create([
        'name' => 'Other community',
        'slug' => 'other-community',
        'description' => 'Other churches',
    ]);
    $church = Church::query()->create(['name' => 'Local church', 'slug' => 'local-church', 'community_id' => $community->id]);
    Church::query()->create(['name' => 'Other church', 'slug' => 'other-church', 'community_id' => $otherCommunity->id]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $this->actingAs($admin)
        ->get(route('admin.multiCongregation.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MultiCongregation')
            ->where('canManageCommunities', false)
            ->has('churches', 1)
            ->where('churches.0.id', $church->id)
            ->has('communities', 1)
            ->where('communities.0.id', $community->id));
});

it('prevents administrators from managing churches outside their community', function () {
    $community = Community::query()->create(['name' => 'Managed', 'slug' => 'managed', 'description' => 'Managed community']);
    $otherCommunity = Community::query()->create(['name' => 'Restricted', 'slug' => 'restricted', 'description' => 'Restricted community']);
    $church = Church::query()->create(['name' => 'Managed church', 'slug' => 'managed-church', 'community_id' => $community->id]);
    $restrictedChurch = Church::query()->create(['name' => 'Restricted church', 'slug' => 'restricted-church', 'community_id' => $otherCommunity->id]);
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $this->actingAs($admin)
        ->putJson("/api/church/{$restrictedChurch->id}", ['name' => 'Forbidden change'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->postJson('/api/church', [
            'name' => 'New local church',
            'slug' => 'new-local-church',
            'status' => 'active',
            'community_id' => $otherCommunity->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('churches', ['slug' => 'new-local-church', 'community_id' => $community->id]);
});

it('allows only system users to manage communities', function () {
    $admin = managementUser();

    $this->actingAs($admin)
        ->postJson('/api/community', ['name' => 'Blocked', 'slug' => 'blocked', 'description' => 'Blocked'])
        ->assertForbidden();

    $system = User::factory()->create(['role' => UserRole::SYSTEM]);
    $this->actingAs($system)
        ->postJson('/api/community', ['name' => 'Global community', 'slug' => 'global-community', 'description' => 'Global'])
        ->assertCreated();

    $this->assertDatabaseHas('communities', ['slug' => 'global-community']);
});

it('shows unpublished church posts in the private post management list', function () {
    $admin = managementUser();
    $post = Post::query()->create([
        'author_id' => $admin->id,
        'church_id' => $admin->church->id,
        'title' => 'Draft update',
        'slug' => 'draft-update',
        'content' => 'Private draft content',
        'published_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('posts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/Index')
            ->where('posts.data.0.id', $post->id));
});

it('lets church editors preview their unpublished posts on the home page', function () {
    $admin = managementUser();
    $admin->church->update(['domain' => 'editorial-preview.test']);
    $post = Post::query()->create([
        'author_id' => $admin->id,
        'church_id' => $admin->profile->church_id,
        'title' => 'Editorial preview',
        'slug' => 'editorial-preview',
        'content' => 'Preview content',
        'published_at' => null,
    ]);

    $this->actingAs($admin)
        ->get('http://editorial-preview.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('latestPosts.0.id', $post->id));

    $this->actingAs($admin)
        ->get(route('posts.public.show', ['slug' => $post->slug]))
        ->assertSuccessful();

    auth()->logout();
    $this->get(route('posts.public.show', ['slug' => $post->slug]))
        ->assertNotFound();
});

it('redirects an inertia post creation back to management with a toast', function () {
    $admin = managementUser();

    $this->actingAs($admin)
        ->withHeader('X-Inertia', 'true')
        ->post('/api/post', [
            'title' => 'Church update',
            'slug' => 'church-update',
            'content' => 'Content for members',
        ])
        ->assertRedirect(route('posts.index'));

    $this->assertDatabaseHas('posts', ['slug' => 'church-update', 'church_id' => $admin->church->id]);
});

it('loads user management without an ambiguous church id selection', function () {
    $admin = managementUser();

    $this->actingAs($admin)
        ->get(route('admin.userManagement.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/UserManagement')->has('users.data'));
});

it('never deletes system or superadmin users', function (UserRole $protectedRole) {
    $system = managementUser(UserRole::SYSTEM);
    $protectedUser = User::factory()->create(['role' => $protectedRole]);

    $this->actingAs($system)
        ->deleteJson("/api/user/{$protectedUser->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $protectedUser->id]);
})->with([UserRole::SYSTEM, UserRole::SUPERADMIN]);
