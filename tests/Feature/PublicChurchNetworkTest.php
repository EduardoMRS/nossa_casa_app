<?php

use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use Inertia\Testing\AssertableInertia as Assert;

test('the approved church hierarchy is publicly visible on the church domain', function () {
    config(['app.url' => 'https://nossa.test']);
    $community = Community::factory()->create();
    $church = Church::factory()->for($community)->create([
        'domain' => 'local.nossa.test',
        'status' => 'active',
    ]);
    $branch = Church::factory()->for($community)->create(['status' => 'active']);
    Network::query()->create([
        'parent_church_id' => $church->id,
        'child_church_id' => $branch->id,
        'community_id' => $community->id,
    ]);

    $this->withHeader('Host', 'local.nossa.test')
        ->get('/network')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Church/Network')
            ->where('network.church.id', $church->id)
            ->where('network.tree.children.0.id', $branch->id)
            ->missing('network.requests')
            ->missing('network.availableChurches'));
});
