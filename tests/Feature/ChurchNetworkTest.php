<?php

use App\Enums\ChurchNetworkRequestStatus;
use App\Models\Church;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;
use App\Services\ChurchNetworkService;
use Illuminate\Validation\ValidationException;

test('a church network link requires approval from the other church', function () {
    $community = Community::factory()->create();
    $parent = Church::factory()->for($community)->create();
    $child = Church::factory()->for($community)->create();
    $parentLeader = User::factory()->create();
    $childLeader = User::factory()->create();
    $service = app(ChurchNetworkService::class);

    $request = $service->request($parent, $parent, $child, $parentLeader);

    expect($request->status)->toBe(ChurchNetworkRequestStatus::PENDING)
        ->and(Network::query()->count())->toBe(0);

    $network = $service->accept($child, $request, $childLeader);

    expect($network->parent_church_id)->toBe($parent->id)
        ->and($network->child_church_id)->toBe($child->id)
        ->and($request->refresh()->status)->toBe(ChurchNetworkRequestStatus::ACCEPTED);
});

test('a headquarters may reorganize churches below it without another approval', function () {
    $community = Community::factory()->create();
    $headquarters = Church::factory()->for($community)->create();
    $regional = Church::factory()->for($community)->create();
    $branch = Church::factory()->for($community)->create();
    Network::query()->create([
        'parent_church_id' => $headquarters->id,
        'child_church_id' => $regional->id,
        'community_id' => $community->id,
    ]);
    $branchNetwork = Network::query()->create([
        'parent_church_id' => $regional->id,
        'child_church_id' => $branch->id,
        'community_id' => $community->id,
    ]);

    $movedNetwork = app(ChurchNetworkService::class)
        ->moveDescendant($headquarters, $branch, $headquarters);

    expect($movedNetwork->parent_church_id)->toBe($headquarters->id)
        ->and($movedNetwork->child_church_id)->toBe($branch->id)
        ->and(Network::query()->whereKey($branchNetwork->id)->exists())->toBeFalse();
});

test('church hierarchy rejects cycles and cross community links', function () {
    $community = Community::factory()->create();
    $otherCommunity = Community::factory()->create();
    $headquarters = Church::factory()->for($community)->create();
    $branch = Church::factory()->for($community)->create();
    $outsider = Church::factory()->for($otherCommunity)->create();
    $leader = User::factory()->create();
    Network::query()->create([
        'parent_church_id' => $headquarters->id,
        'child_church_id' => $branch->id,
        'community_id' => $community->id,
    ]);
    $service = app(ChurchNetworkService::class);

    expect(fn () => $service->request($headquarters, $branch, $headquarters, $leader))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->request($headquarters, $headquarters, $outsider, $leader))
        ->toThrow(ValidationException::class);
});


test('church network overview exposes ancestors and the descendant tree', function () {
    $community = Community::factory()->create();
    $headquarters = Church::factory()->for($community)->create();
    $regional = Church::factory()->for($community)->create();
    $branch = Church::factory()->for($community)->create();
    Network::query()->create([
        'parent_church_id' => $headquarters->id,
        'child_church_id' => $regional->id,
        'community_id' => $community->id,
    ]);
    Network::query()->create([
        'parent_church_id' => $regional->id,
        'child_church_id' => $branch->id,
        'community_id' => $community->id,
    ]);

    $overview = app(\App\Services\ChurchNetworkSettingsData::class)->forChurch($regional);

    expect($overview['ancestors'])->toHaveCount(1)
        ->and($overview['ancestors'][0]['id'])->toBe($headquarters->id)
        ->and($overview['tree']['id'])->toBe($regional->id)
        ->and($overview['tree']['children'][0]['id'])->toBe($branch->id)
        ->and($overview['stats'])->toMatchArray([
            'direct_branches' => 1,
            'all_branches' => 1,
            'levels' => 1,
        ]);
});
