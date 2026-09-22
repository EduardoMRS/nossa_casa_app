<?php

use App\Enums\ChurchStatus;
use App\Models\Church;
use App\Models\Community;
use App\Models\Event;
use App\Models\Network;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('community pages prioritize local churches and expose the organizational tree', function () {
    $community = Community::factory()->create([
        'name' => 'Regional Community',
        'slug' => 'regional-community',
    ]);
    $headquarters = Church::factory()->for($community)->create([
        'name' => 'Central Temple',
        'domain' => 'central-tree.test',
    ]);
    $nearbyBranch = Church::factory()->for($community)->create([
        'name' => 'Nearby Congregation',
        'domain' => 'nearby-tree.test',
    ]);
    $farBranch = Church::factory()->for($community)->create([
        'name' => 'Far Congregation',
        'domain' => 'far-tree.test',
    ]);
    Church::factory()->for($community)->create([
        'name' => 'Inactive Unit',
        'status' => ChurchStatus::INACTIVE,
    ]);

    $headquarters->address()->create(['city' => 'Manaus', 'state' => 'AM', 'street' => 'Central Avenue', 'zipcode' => '69000-000', 'latitude' => -3.1190, 'longitude' => -60.0217]);
    $nearbyBranch->address()->create(['city' => 'Manaus', 'state' => 'AM', 'street' => 'Nearby Avenue', 'zipcode' => '69000-001', 'latitude' => -3.1200, 'longitude' => -60.0200]);
    $farBranch->address()->create(['city' => 'Boa Vista', 'state' => 'RR', 'street' => 'Far Avenue', 'zipcode' => '69300-000', 'latitude' => 2.8235, 'longitude' => -60.6758]);

    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $headquarters->id,
        'child_church_id' => $nearbyBranch->id,
    ]);
    Network::query()->create([
        'community_id' => $community->id,
        'parent_church_id' => $headquarters->id,
        'child_church_id' => $farBranch->id,
    ]);

    $member = User::factory()->create();
    $nearbyBranch->assignMember($member);
    Event::query()->create([
        'church_id' => $nearbyBranch->id,
        'author_id' => $member->id,
        'title' => 'Local Gathering',
        'slug' => 'local-gathering',
        'start_time' => now()->addWeek(),
        'end_time' => now()->addWeek()->addHours(2),
    ]);

    $this->get('/en/communities/regional-community?latitude=-3.1200&longitude=-60.0200')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/CommunityShow')
            ->where('community.name', 'Regional Community')
            ->where('stats.churches', 3)
            ->where('stats.members', 1)
            ->where('stats.upcoming_events', 1)
            ->has('churches', 3)
            ->where('churches.0.id', $nearbyBranch->id)
            ->where('churches.0.distance_km', fn (float $distance): bool => $distance < 1)
            ->where('tree.0.id', $headquarters->id)
            ->has('tree.0.children', 2)
            ->where('locationApplied', true));

    $this->get('http://nearby-tree.test/communities/regional-community')
        ->assertRedirect(rtrim((string) config('app.url'), '/').'/en/communities/regional-community');
});

test('portal community cards link to their public detail page', function () {
    $community = Community::factory()->create([
        'name' => 'Linked Community',
        'slug' => 'linked-community',
    ]);
    Church::factory()->for($community)->create();
    Community::factory()->create(['name' => 'Empty Community', 'slug' => 'empty-community']);

    $this->get('/en')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Index')
            ->has('communities', 1)
            ->where('communities.0.url', route('communities.show', 'linked-community')));
});
