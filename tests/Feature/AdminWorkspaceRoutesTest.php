<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
});

function createAdminUserWithChurch(): User
{
    $community = Community::query()->create([
        'name' => 'Comunidade Teste '.Str::random(6),
        'description' => 'Comunidade para testes de rotas administrativas',
        'slug' => 'comunidade-'.Str::lower(Str::random(8)),
    ]);

    $church = Church::query()->create([
        'name' => 'Igreja Teste '.Str::random(6),
        'slug' => 'igreja-'.Str::lower(Str::random(8)),
        'community_id' => $community->id,
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'role' => UserRole::ADMIN,
    ]);

    UserProfile::query()->create([
        'user_id' => $user->id,
        'church_id' => $church->id,
        'location_lang' => 'pt-BR',
    ]);

    return $user;
}

it('allows admin to open all admin workspace routes', function () {
    $admin = createAdminUserWithChurch();

    expect(parse_url(route('admin.events.index'), PHP_URL_PATH))->toBe('/dashboard/eventos')
        ->and(parse_url(route('posts.index'), PHP_URL_PATH))->toBe('/dashboard/posts');

    $routes = [
        'admin.branding.edit',
        'admin.highlights.index',
        'admin.events.index',
        'admin.galleryModeration.index',
        'admin.wallModeration.index',
        'admin.libraryVerse.index',
        'admin.forms.index',
        'admin.kidsMinistry.index',
        'admin.userManagement.index',
        'admin.multiCongregation.index',
        'admin.classrooms.index',
    ];

    foreach ($routes as $routeName) {
        $this->actingAs($admin)
            ->get(route($routeName))
            ->assertSuccessful();
    }

    $this->actingAs($admin)->get(route('admin.prayerRequests.index'))->assertRedirect('/dashboard/minhas-oracoes');
    $this->actingAs($admin)->get(route('admin.myPrayers.index'))->assertRedirect('/minhas-oracoes');
});

it('blocks members from admin workspace routes', function () {
    $member = User::factory()->create([
        'role' => UserRole::MEMBER,
    ]);

    $routes = [
        'admin.branding.edit',
        'admin.highlights.index',
        'admin.events.index',
        'admin.galleryModeration.index',
        'admin.wallModeration.index',
        'admin.libraryVerse.index',
        'admin.forms.index',
        'admin.prayerRequests.index',
        'admin.kidsMinistry.index',
        'admin.myPrayers.index',
        'admin.userManagement.index',
        'admin.multiCongregation.index',
        'admin.classrooms.index',
        'admin.logsMetrics.index',
    ];

    foreach ($routes as $routeName) {
        $this->actingAs($member)
            ->get(route($routeName))
            ->assertForbidden();
    }
});

it('restricts operational logs and metrics to system users', function () {
    $admin = createAdminUserWithChurch();
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);

    $this->actingAs($admin)->get(route('admin.logsMetrics.index'))->assertForbidden();
    $this->actingAs($system)->get(route('admin.logsMetrics.index'))->assertSuccessful();
});
