<?php

use App\Enums\LiveStreamStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\LiveStream;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

function createDomainChurch(string $name, string $domain): Church
{
    $community = Community::query()->create([
        'name' => $name.' Community',
        'slug' => str($name)->slug()->append('-community')->toString(),
        'description' => 'Community for domain tenancy tests.',
    ]);

    return Church::query()->create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'domain' => $domain,
        'community_id' => $community->id,
        'status' => 'active',
    ]);
}

test('main application domain renders the institutional portal', function () {
    $alpha = createDomainChurch('Alpha Church', 'alpha.test');
    $beta = createDomainChurch('Beta Church', 'beta.test');
    LiveStream::factory()->create([
        'church_id' => $alpha->id,
        'status' => LiveStreamStatus::LIVE,
        'is_public' => true,
    ]);
    LiveStream::factory()->create([
        'church_id' => $beta->id,
        'status' => LiveStreamStatus::LIVE,
        'is_public' => false,
    ]);

    $this->get('http://platform.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Index')
            ->where('communities.0.churches.0.domain', 'alpha.test')
            ->where('communities.0.churches.0.is_live', true)
            ->where('communities.1.churches.0.is_live', false));
});

test('custom domain renders only content from its church', function () {
    $alpha = createDomainChurch('Alpha Church', 'alpha.test');
    $beta = createDomainChurch('Beta Church', 'beta.test');
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    Post::query()->create([
        'church_id' => $alpha->id,
        'author_id' => $author->id,
        'title' => 'Alpha update',
        'slug' => 'alpha-update',
        'content' => 'Alpha content',
        'published_at' => now(),
    ]);
    Post::query()->create([
        'church_id' => $beta->id,
        'author_id' => $author->id,
        'title' => 'Beta update',
        'slug' => 'beta-update',
        'content' => 'Beta content',
        'published_at' => now(),
    ]);

    $this->get('http://alpha.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('latestPosts', 1)
            ->where('latestPosts.0.slug', 'alpha-update')
            ->where('churchContext.church.id', $alpha->id));

    $this->get('http://alpha.test/publicacoes/beta-update')->assertNotFound();
});

test('existing session remains authenticated with public access on another church domain', function () {
    $alpha = createDomainChurch('Alpha Church', 'alpha.test');
    $beta = createDomainChurch('Beta Church', 'beta.test');
    $user = User::factory()->create();
    $alpha->assignMember($user);

    $this->actingAs($user)
        ->get('http://beta.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('churchContext.isForeignChurch', true)
            ->where('permissions.accessDashboard', false));

    $this->assertAuthenticatedAs($user);
});

test('login in an unrelated church allows public access and optional membership transfer', function () {
    $alpha = createDomainChurch('Alpha Church', 'alpha.test');
    $beta = createDomainChurch('Beta Church', 'beta.test');
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $alpha->assignMember($user);

    $this->post('http://beta.test/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $this->assertNull(session('church_membership_pending'));

    $this->get('http://beta.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('churchContext.isForeignChurch', true));

    $this->post('http://beta.test/church-membership/switch', ['confirmed' => true])->assertRedirect();
    expect($user->fresh()->profile->church_id)->toBe($beta->id)
        ->and($user->fresh()->role)->toBe(UserRole::MEMBER);
});

test('login on the user church domain persists through the dashboard request', function () {
    $church = createDomainChurch('Alpha Church', 'alpha.test');
    $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($user);

    $this->post('http://alpha.test/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->get('http://alpha.test/dashboard')->assertSuccessful();
    $this->assertAuthenticatedAs($user);
});

test('login on the main domain redirects through a single use church handoff', function () {
    $church = createDomainChurch('Alpha Church', 'alpha.test');
    $user = User::factory()->create();
    $church->assignMember($user);

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->post('http://platform.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response->assertConflict();
    $location = $response->headers->get('X-Inertia-Location');
    expect($location)->toStartWith('https://alpha.test/auth/handoff?token=');

    Auth::guard('web')->logout();
    $this->withoutHeader('X-Inertia');
    $this->get($location)->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    $this->get($location)->assertForbidden();
});

test('system administrator can configure a normalized custom church domain', function () {
    $church = createDomainChurch('Configurable Church', 'old-domain.test');
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);

    $this->actingAs($system)
        ->putJson("http://platform.test/api/church/{$church->id}", [
            'domain' => 'https://NEW-DOMAIN.test/welcome',
        ])
        ->assertOk()
        ->assertJsonPath('domain', 'new-domain.test');

    $this->actingAs($system)
        ->putJson("http://platform.test/api/church/{$church->id}", [
            'domain' => 'platform.test',
        ])
        ->assertUnprocessable();
});
