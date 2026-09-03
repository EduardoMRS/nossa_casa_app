<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    config(['services.geocoding.enabled' => false]);
    Storage::fake('local');
    $this->withoutVite();
});

function requiredChurchAddress(): array
{
    return [
        'street' => 'Main Street',
        'neighborhood' => 'Center',
        'city' => 'Manaus',
        'state' => 'AM',
        'zipcode' => '69000-000',
        'country' => 'Brasil',
    ];
}

test('church request accepts a private proof document visible only to reviewers', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $outsider = User::factory()->create();
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $requester->profile()->create(['community_id' => $community->id]);

    $this->actingAs($requester)->post('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Documented Church',
        'slug' => 'documented-church',
        'domain' => 'documented.platform.test',
        ...requiredChurchAddress(),
        'proof_document' => UploadedFile::fake()->create('authorization.pdf', 120, 'application/pdf'),
    ])->assertCreated();

    $registrationRequest = ChurchRegistrationRequest::query()->firstOrFail();
    expect($registrationRequest->proof_document_name)->toBe('authorization.pdf');
    Storage::disk('local')->assertExists($registrationRequest->proof_document_path);

    $proofUrl = "http://platform.test/onboarding/churches/{$registrationRequest->id}/proof";
    $this->actingAs($outsider)->get($proofUrl)->assertForbidden();
    $this->actingAs($owner)->get($proofUrl)->assertSuccessful()->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('church request rejects a domain already reserved by a pending request', function () {
    $requester = User::factory()->create();
    $community = Community::factory()->create(['owner_id' => $requester->id]);
    $requester->profile()->create(['community_id' => $community->id]);
    ChurchRegistrationRequest::factory()->create([
        'community_id' => $community->id,
        'domain' => 'reserved.platform.test',
        'status' => 'pending',
    ]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Duplicate Church',
        'slug' => 'duplicate-church',
        'domain' => 'reserved.platform.test',
        ...requiredChurchAddress(),
    ])->assertUnprocessable()->assertJsonValidationErrors('domain');
});

test('church request geocodes structured address and falls back to manual coordinates', function () {
    config(['services.geocoding.enabled' => true]);
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '-22.9068', 'lon' => '-43.1729'],
        ]),
    ]);
    config(['services.geocoding.url' => 'https://nominatim.openstreetmap.org/search']);

    $requester = User::factory()->create();
    $community = Community::factory()->create(['owner_id' => $requester->id]);
    $requester->profile()->create(['community_id' => $community->id]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Mapped Church',
        'slug' => 'mapped-church',
        'domain' => 'mapped.platform.test',
        'street' => 'Rua da Paz',
        'number' => '100',
        'neighborhood' => 'Centro',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'zipcode' => '20000-000',
        'country' => 'Brasil',
    ])->assertCreated();

    $registrationRequest = ChurchRegistrationRequest::query()->firstOrFail();
    expect($registrationRequest->latitude)->toBe(-22.9068)
        ->and($registrationRequest->longitude)->toBe(-43.1729);

    Http::fake(['nominatim.openstreetmap.org/*' => Http::failedConnection()]);
    $fallbackRequest = $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Manual Church',
        'slug' => 'manual-church',
        'domain' => 'manual.platform.test',
        'street' => 'Rua Manual',
        'neighborhood' => 'Center',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'zipcode' => '20000-000',
        'country' => 'Brasil',
        'latitude' => '-23.55',
        'longitude' => '-46.63',
    ])->assertCreated();

    expect($fallbackRequest->json('latitude'))->toBe(-23.55)
        ->and($fallbackRequest->json('longitude'))->toBe(-46.63);
});

test('superadmin church requests are approved immediately, including a selected parent', function () {
    $admin = User::factory()->create(['role' => UserRole::SUPERADMIN]);
    $community = Community::factory()->create();
    $parentChurch = Church::factory()->for($community)->create();

    $response = $this->actingAs($admin)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'parent_church_id' => $parentChurch->id,
        'name' => 'Immediate Branch',
        'slug' => 'immediate-branch',
        'domain' => 'immediate.platform.test',
        ...requiredChurchAddress(),
    ])->assertOk();

    $churchId = $response->json('church.id');
    expect($response->json('request.status'))->toBe('approved')
        ->and($churchId)->not->toBeNull();
    expect(Network::query()
        ->where('parent_church_id', $parentChurch->id)
        ->where('child_church_id', $churchId)
        ->exists())->toBeTrue();
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
        'street' => 'Faith Street',
        'number' => '123',
        'neighborhood' => 'Central',
        'city' => 'Springfield',
        'state' => 'SP',
        'zipcode' => '01000-000',
        'country' => 'Brasil',
        'complement' => 'Room 2',
    ])->assertCreated();

    $registrationRequest = ChurchRegistrationRequest::query()->firstOrFail();
    expect($registrationRequest->domain)->toBe('rcspr.platform.test')
        ->and($registrationRequest->status)->toBe('pending');

    $this->actingAs($owner)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertOk()
        ->assertJsonPath('church.domain', 'rcspr.platform.test');

    expect($registrationRequest->fresh()->status)->toBe('approved')
        ->and($registrationRequest->fresh()->approvedChurch)->not->toBeNull()
        ->and($registrationRequest->fresh()->approvedChurch->address()->first()->only([
            'street', 'number', 'neighborhood', 'city', 'state', 'zipcode', 'country', 'complement',
        ]))->toMatchArray([
            'street' => 'Faith Street',
            'number' => '123',
            'neighborhood' => 'Central',
            'city' => 'Springfield',
            'state' => 'SP',
            'zipcode' => '01000-000',
            'country' => 'Brasil',
            'complement' => 'Room 2',
        ])
        ->and($requester->fresh()->profile->church_id)->toBe($registrationRequest->fresh()->approved_church_id)
        ->and($requester->fresh()->role)->toBe(UserRole::CHURCH_LEADER);
});

test('unrelated user cannot approve a church request', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
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


test('selected parent church exclusively reviews and receives the approved church as a branch', function () {
    $communityOwner = User::factory()->create();
    $requester = User::factory()->create();
    $parentLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $community = Community::factory()->create(['owner_id' => $communityOwner->id]);
    $parentChurch = Church::factory()->create(['community_id' => $community->id]);
    $otherChurch = Church::factory()->create(['community_id' => $community->id]);

    $requester->profile()->create(['community_id' => $community->id]);
    $parentLeader->profile()->create([
        'community_id' => $community->id,
        'church_id' => $parentChurch->id,
    ]);
    $otherLeader->profile()->create([
        'community_id' => $community->id,
        'church_id' => $otherChurch->id,
    ]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'parent_church_id' => $parentChurch->id,
        'name' => 'Requested Branch',
        'slug' => 'requested-branch',
        'domain' => 'requested-branch.platform.test',
        ...requiredChurchAddress(),
    ])->assertCreated();

    $registrationRequest = ChurchRegistrationRequest::query()->firstOrFail();
    expect($registrationRequest->requested_parent_church_id)->toBe($parentChurch->id);

    $this->actingAs($communityOwner)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertForbidden();
    $this->actingAs($otherLeader)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertForbidden();

    $this->actingAs($parentLeader)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertOk();

    $approvedChurchId = $registrationRequest->fresh()->approved_church_id;
    expect(Network::query()
        ->where('parent_church_id', $parentChurch->id)
        ->where('child_church_id', $approvedChurchId)
        ->where('community_id', $community->id)
        ->exists())->toBeTrue();
});

test('parent-directed registration is shown only to the selected parent church', function () {
    $communityOwner = User::factory()->create();
    $requester = User::factory()->create();
    $parentLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $community = Community::factory()->create(['owner_id' => $communityOwner->id]);
    $parentChurch = Church::factory()->create(['community_id' => $community->id]);
    $otherChurch = Church::factory()->create(['community_id' => $community->id]);

    $requester->profile()->create(['community_id' => $community->id]);
    $parentLeader->profile()->create([
        'community_id' => $community->id,
        'church_id' => $parentChurch->id,
    ]);
    $otherLeader->profile()->create([
        'community_id' => $community->id,
        'church_id' => $otherChurch->id,
    ]);
    ChurchRegistrationRequest::factory()->create([
        'requester_id' => $requester->id,
        'community_id' => $community->id,
        'requested_parent_church_id' => $parentChurch->id,
        'status' => 'pending',
    ]);

    $this->actingAs($parentLeader)
        ->get('http://platform.test/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Index')
            ->has('reviewableRequests', 1)
            ->where('reviewableRequests.0.requested_parent_church.id', $parentChurch->id));

    $this->actingAs($otherLeader)
        ->get('http://platform.test/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Index')
            ->has('reviewableRequests', 0));

    $this->actingAs($communityOwner)
        ->get('http://platform.test/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Index')
            ->has('reviewableRequests', 0));
});

test('registration rejects a parent church from another community', function () {
    $requester = User::factory()->create();
    $community = Community::factory()->create(['owner_id' => $requester->id]);
    $otherCommunity = Community::factory()->create();
    $foreignChurch = Church::factory()->create(['community_id' => $otherCommunity->id]);
    $requester->profile()->create(['community_id' => $community->id]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'parent_church_id' => $foreignChurch->id,
        'name' => 'Invalid Branch',
        'slug' => 'invalid-branch',
        'domain' => 'invalid-branch.platform.test',
        ...requiredChurchAddress(),
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_church_id');
});
