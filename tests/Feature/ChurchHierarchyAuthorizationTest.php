<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChurchAccess;
use Tests\TestCase;

/**
 * Builds: headquarters -> branch -> sub-branch, plus a sibling branch and an
 * unrelated church, each with a church leader.
 *
 * @return array<string, mixed>
 */
function churchTree(): array
{
    $community = Community::factory()->create();
    $make = fn (string $name): Church => Church::factory()->create(['name' => $name, 'community_id' => $community->id]);

    $hq = $make('Matriz');
    $branch = $make('Filial');
    $subBranch = $make('Filial da filial');
    $sibling = $make('Filial irmã');
    $unrelated = Church::factory()->create(['name' => 'Outra']);

    foreach ([[$hq, $branch], [$branch, $subBranch], [$hq, $sibling]] as [$parent, $child]) {
        Network::query()->create([
            'parent_church_id' => $parent->id,
            'child_church_id' => $child->id,
            'community_id' => $community->id,
        ]);
    }

    $leader = function (Church $church): User {
        $user = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
        $church->assignMember($user);

        return $user->fresh();
    };

    return [
        'community' => $community,
        'hq' => $hq, 'branch' => $branch, 'subBranch' => $subBranch,
        'sibling' => $sibling, 'unrelated' => $unrelated,
        'hqLeader' => $leader($hq), 'branchLeader' => $leader($branch),
        'siblingLeader' => $leader($sibling),
    ];
}

function apiAs(User $user): TestCase
{
    // Guards cache the first authenticated user for the whole test.
    app('auth')->forgetGuards();

    return test()->withToken($user->createToken('hierarchy-test')->plainTextToken);
}

test('headquarters reaches branches and branches of branches, never the other way around', function (): void {
    $t = churchTree();
    $access = app(ChurchAccess::class);

    expect($access->canManageChurch($t['hqLeader'], $t['branch']->id))->toBeTrue()
        ->and($access->canManageChurch($t['hqLeader'], $t['subBranch']->id))->toBeTrue()
        ->and($access->canManageChurch($t['hqLeader'], $t['unrelated']->id))->toBeFalse()
        ->and($access->canManageChurch($t['branchLeader'], $t['subBranch']->id))->toBeTrue()
        ->and($access->canManageChurch($t['branchLeader'], $t['hq']->id))->toBeFalse()
        ->and($access->canManageChurch($t['branchLeader'], $t['sibling']->id))->toBeFalse()
        ->and($access->canManageChurch($t['siblingLeader'], $t['branch']->id))->toBeFalse();
});

test('community leadership manages no church and system bypasses everything but billing', function (): void {
    $t = churchTree();
    $access = app(ChurchAccess::class);

    $superadmin = User::factory()->create(['role' => UserRole::SUPERADMIN]);
    $t['hq']->assignMember($superadmin);
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);

    expect($access->canManageChurch($superadmin->fresh(), $t['hq']->id))->toBeFalse()
        ->and($access->canManageChurch($superadmin->fresh(), $t['branch']->id))->toBeFalse()
        ->and($access->canManageChurch($system, $t['unrelated']->id))->toBeTrue()
        ->and($access->canManageTreasury($system, $t['hq']->id))->toBeFalse()
        ->and($access->canManageTreasury($t['hqLeader'], $t['hq']->id))->toBeTrue()
        ->and($access->canManageTreasury($t['hqLeader'], $t['branch']->id))->toBeFalse();
});

test('a branch cannot edit its headquarters church but the headquarters can edit the branch', function (): void {
    $t = churchTree();

    apiAs($t['branchLeader'])->putJson("/api/church/{$t['hq']->id}", ['name' => 'Tomada'])->assertForbidden();
    apiAs($t['siblingLeader'])->putJson("/api/church/{$t['branch']->id}", ['name' => 'Tomada'])->assertForbidden();
    apiAs($t['hqLeader'])->putJson("/api/church/{$t['subBranch']->id}", ['name' => 'Renomeada'])->assertSuccessful();

    expect($t['hq']->refresh()->name)->toBe('Matriz')
        ->and($t['branch']->refresh()->name)->toBe('Filial')
        ->and($t['subBranch']->refresh()->name)->toBe('Renomeada');
});

test('a church leader cannot delete its own church nor its headquarters', function (): void {
    $t = churchTree();

    apiAs($t['branchLeader'])->deleteJson("/api/church/{$t['branch']->id}")->assertForbidden();
    apiAs($t['branchLeader'])->deleteJson("/api/church/{$t['hq']->id}")->assertForbidden();

    expect(Church::query()->whereKey([$t['hq']->id, $t['branch']->id])->count())->toBe(2);
});

test('community leadership cannot edit churches', function (): void {
    $t = churchTree();
    $superadmin = User::factory()->create(['role' => UserRole::SUPERADMIN]);
    $superadmin->profile()->updateOrCreate(['user_id' => $superadmin->id], ['community_id' => $t['community']->id]);

    apiAs($superadmin->fresh())->putJson("/api/church/{$t['branch']->id}", ['name' => 'Tomada'])->assertForbidden();

    expect($t['branch']->refresh()->name)->toBe('Filial');
});

test('church settings follow the hierarchy', function (): void {
    $t = churchTree();
    $hqSetting = Setting::query()->create(['church_id' => $t['hq']->id, 'options' => ['currency' => 'BRL']]);
    $branchSetting = Setting::query()->create(['church_id' => $t['branch']->id, 'options' => ['currency' => 'BRL']]);

    apiAs($t['branchLeader'])->putJson("/api/settings/{$hqSetting->id}", ['options' => ['currency' => 'USD']])->assertForbidden();
    apiAs($t['branchLeader'])->postJson('/api/settings', ['church_id' => $t['hq']->id, 'options' => []])->assertForbidden();
    apiAs($t['hqLeader'])->putJson("/api/settings/{$branchSetting->id}", ['options' => ['currency' => 'EUR']])->assertSuccessful();

    expect($hqSetting->refresh()->options['currency'])->toBe('BRL')
        ->and($branchSetting->refresh()->options['currency'])->toBe('EUR');

    $listed = collect(apiAs($t['branchLeader'])->getJson('/api/settings')->assertSuccessful()->json('data'))->pluck('church_id');
    expect($listed)->not->toContain($t['hq']->id);
});

test('users can only be created and listed inside the managed church tree', function (): void {
    $t = churchTree();
    $payload = fn (Church $church): array => [
        'first_name' => 'Novo', 'last_name' => 'Membro',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password123', 'password_confirmation' => 'password123',
        'role' => UserRole::MEMBER->value, 'church_id' => $church->id,
    ];

    apiAs($t['branchLeader'])->postJson('/api/users', $payload($t['hq']))->assertForbidden();
    apiAs($t['branchLeader'])->postJson('/api/users', [...$payload($t['branch']), 'role' => UserRole::SUPERADMIN->value])->assertForbidden();
    apiAs($t['hqLeader'])->postJson('/api/users', $payload($t['branch']))->assertCreated();

    $listedIds = collect(apiAs($t['branchLeader'])->getJson('/api/users')->assertSuccessful()->json('data'))->pluck('id');
    expect($listedIds)->not->toContain($t['hqLeader']->id)
        ->and($listedIds)->toContain($t['branchLeader']->id);

    apiAs($t['branchLeader'])->getJson("/api/users/{$t['hqLeader']->id}")->assertForbidden();
    apiAs($t['branchLeader'])->putJson("/api/users/{$t['hqLeader']->id}", ['first_name' => 'X'])->assertForbidden();
});

test('a forged church header cannot create content in an unrelated church', function (): void {
    $t = churchTree();

    apiAs($t['branchLeader'])
        ->withHeader('X-Church-ID', $t['unrelated']->id)
        ->postJson('/api/posts', ['title' => 'Invasão', 'content' => 'x'])
        ->assertForbidden();

    apiAs($t['branchLeader'])
        ->withHeader('X-Church-ID', $t['hq']->id)
        ->postJson('/api/posts', ['title' => 'Invasão', 'content' => 'x'])
        ->assertForbidden();

    expect(Post::query()->count())->toBe(0);
});

test('the headquarters can publish content in a branch', function (): void {
    $t = churchTree();

    apiAs($t['hqLeader'])
        ->withHeader('X-Church-ID', $t['subBranch']->id)
        ->postJson('/api/posts', ['title' => 'Aviso da matriz', 'content' => 'x'])
        ->assertCreated();

    expect(Post::query()->sole()->church_id)->toBe($t['subBranch']->id);
});

test('a branch cannot open the headquarters dashboard but the headquarters opens the branch one', function (): void {
    $this->withoutVite();
    $t = churchTree();
    $t['hq']->update(['domain' => 'matriz.test']);
    $t['branch']->update(['domain' => 'filial.test']);

    $this->actingAs($t['branchLeader'])->get('http://matriz.test/dashboard')->assertForbidden();
    $this->actingAs($t['hqLeader'])->get('http://filial.test/dashboard')->assertSuccessful();
});

test('the public community endpoint does not expose its members', function (): void {
    $t = churchTree();

    $community = $this->getJson("/api/communities/{$t['community']->id}")->assertSuccessful()->json();

    expect($community)->not->toHaveKey('members');
});
