<?php

use App\Enums\UserRole;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\User;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('church registration requires authentication', function () {
    $this->post('http://platform.test/onboarding/churches', [])->assertRedirect();
    $this->assertGuest();
});

test('authenticated user can register a community and becomes its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('http://platform.test/onboarding/communities', [
        'name' => 'New Community',
        'slug' => 'new-community',
        'description' => 'A new faith community.',
    ])->assertCreated();

    $community = Community::query()->where('slug', 'new-community')->firstOrFail();
    expect($community->owner_id)->toBe($user->id)
        ->and($user->fresh()->profile->community_id)->toBe($community->id);
});

test('community member can request a church and community owner can approve it', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $community = Community::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Approval Community',
        'slug' => 'approval-community',
        'description' => 'Community responsible for approval.',
    ]);
    $owner->profile()->create(['community_id' => $community->id]);
    $requester->profile()->create(['community_id' => $community->id]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Requested Church',
        'slug' => 'requested-church',
        'domain' => 'https://requested.test/welcome',
        'description' => 'Church awaiting community review.',
        'contact_email' => 'contact@requested.test',
        'address' => '123 Faith Street',
    ])->assertCreated();

    $registrationRequest = ChurchRegistrationRequest::query()->firstOrFail();
    expect($registrationRequest->domain)->toBe('requested.test')
        ->and($registrationRequest->status)->toBe('pending');

    $this->actingAs($owner)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertOk()
        ->assertJsonPath('church.domain', 'requested.test');

    expect($registrationRequest->fresh()->status)->toBe('approved')
        ->and($registrationRequest->fresh()->approvedChurch)->not->toBeNull()
        ->and($requester->fresh()->profile->church_id)->toBe($registrationRequest->fresh()->approved_church_id)
        ->and($requester->fresh()->role)->toBe(UserRole::ADMIN);
});

test('unrelated user cannot approve a church request', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create(['role' => UserRole::ADMIN]);
    $community = Community::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Protected Community',
        'slug' => 'protected-community',
        'description' => 'Protected approval community.',
    ]);
    $registrationRequest = ChurchRegistrationRequest::query()->create([
        'requester_id' => $owner->id,
        'community_id' => $community->id,
        'name' => 'Protected Church',
        'slug' => 'protected-church',
        'domain' => 'protected.test',
        'status' => 'pending',
    ]);

    $this->actingAs($outsider)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertForbidden();
});
