<?php

use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;

test('a distant member receives nearby network churches before community fallback', function () {
    $community = Community::factory()->create();
    $memberChurch = Church::factory()->for($community)->create(['domain' => 'member.localhost']);
    $networkChurch = Church::factory()->for($community)->create(['domain' => 'nearby.localhost']);
    $communityChurch = Church::factory()->for($community)->create(['domain' => 'community.localhost']);
    $memberChurch->address()->create(['latitude' => 1, 'longitude' => 1]);
    $networkChurch->address()->create(['latitude' => 0.05, 'longitude' => 0.05]);
    $communityChurch->address()->create(['latitude' => 0.02, 'longitude' => 0.02]);
    Network::query()->create([
        'parent_church_id' => $memberChurch->id,
        'child_church_id' => $networkChurch->id,
        'community_id' => $community->id,
    ]);
    $user = User::factory()->create();
    $memberChurch->assignMember($user);

    $response = $this->actingAs($user)->getJson(
        '/api/church-proximity?latitude=0&longitude=0',
    );

    $response
        ->assertOk()
        ->assertJsonPath('should_prompt', true)
        ->assertJsonPath('source', 'network')
        ->assertJsonCount(1, 'alternatives')
        ->assertJsonPath('alternatives.0.id', $networkChurch->id);
});

test('proximity does not prompt users without a church', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/church-proximity?latitude=0&longitude=0')
        ->assertOk()
        ->assertJson([
            'should_prompt' => false,
            'alternatives' => [],
        ]);
});
