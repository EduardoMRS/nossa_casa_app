<?php

use App\Enums\UserRole;
use App\Mail\ChurchRegistrationRequestedMail;
use App\Models\Church;
use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    Mail::fake();
    $this->withoutVite();
});

test('selected parent church leaders receive the registration request email', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $parentLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $otherLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $community = Community::factory()->create(['owner_id' => $owner->id]);
    $parentChurch = Church::factory()->create(['community_id' => $community->id]);
    $otherChurch = Church::factory()->create(['community_id' => $community->id]);

    $requester->profile()->create(['community_id' => $community->id]);
    $parentLeader->profile()->create(['community_id' => $community->id, 'church_id' => $parentChurch->id]);
    $otherLeader->profile()->create(['community_id' => $community->id, 'church_id' => $otherChurch->id]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'parent_church_id' => $parentChurch->id,
        'name' => 'Requested Branch',
        'slug' => 'requested-branch',
        'domain' => 'requested-branch.platform.test',
    ])->assertCreated();

    Mail::assertQueued(ChurchRegistrationRequestedMail::class, fn ($mail) => $mail->hasTo($parentLeader->email));
    Mail::assertNotQueued(ChurchRegistrationRequestedMail::class, fn ($mail) => $mail->hasTo($owner->email));
    Mail::assertNotQueued(ChurchRegistrationRequestedMail::class, fn ($mail) => $mail->hasTo($otherLeader->email));
});

test('community reviewers receive the registration request email when no parent is selected', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $communityLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $outsider = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $community = Community::factory()->create(['owner_id' => $owner->id]);

    $requester->profile()->create(['community_id' => $community->id]);
    $communityLeader->profile()->create(['community_id' => $community->id]);
    $outsider->profile()->create();

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Requested Church',
        'slug' => 'requested-church',
        'domain' => 'requested-church.platform.test',
    ])->assertCreated();

    Mail::assertQueued(ChurchRegistrationRequestedMail::class, 2);
    Mail::assertQueued(ChurchRegistrationRequestedMail::class, fn ($mail) => $mail->hasTo($owner->email));
    Mail::assertQueued(ChurchRegistrationRequestedMail::class, fn ($mail) => $mail->hasTo($communityLeader->email));
    Mail::assertNotQueued(ChurchRegistrationRequestedMail::class, fn ($mail) => $mail->hasTo($outsider->email));
});

test('unassigned user can request an existing community and location is applied after approval', function () {
    $owner = User::factory()->create();
    $requester = User::factory()->create();
    $community = Community::factory()->create([
        'owner_id' => $owner->id,
        'default_locale' => 'en',
    ]);

    $this->actingAs($requester)->postJson('http://platform.test/onboarding/churches', [
        'community_id' => $community->id,
        'name' => 'Unassigned Church',
        'slug' => 'unassigned-church',
        'domain' => 'unassigned-church.platform.test',
        'address' => '123 Faith Street',
        'latitude' => -10.88,
        'longitude' => -61.95,
        'locale' => 'en',
    ])->assertCreated();

    $registrationRequest = ChurchRegistrationRequest::query()->firstOrFail();
    expect($registrationRequest->locale)->toBe('en')
        ->and($registrationRequest->latitude)->toBe(-10.88)
        ->and($registrationRequest->longitude)->toBe(-61.95);

    $this->actingAs($owner)
        ->postJson("http://platform.test/onboarding/churches/{$registrationRequest->id}/approve")
        ->assertOk();

    $church = $registrationRequest->fresh()->approvedChurch;
    expect($church->address()->first()?->street)->toBe('123 Faith Street')
        ->and($church->address()->first()?->latitude)->toBe(-10.88)
        ->and(data_get($church->settings()->first()?->options, 'default_locale'))->toBe('en')
        ->and($requester->fresh()->profile?->community_id)->toBe($community->id);
});

test('new community stores its default language and map location', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('http://platform.test/onboarding/communities', [
        'name' => 'Mapped Community',
        'slug' => 'mapped-community',
        'description' => 'A community with a default language and location.',
        'default_locale' => 'pt',
        'address' => 'Avenida Brasil, 1000',
        'latitude' => -10.88,
        'longitude' => -61.95,
    ])->assertCreated();

    $community = Community::query()->where('slug', 'mapped-community')->firstOrFail();
    expect($community->default_locale)->toBe('pt')
        ->and($community->address?->street)->toBe('Avenida Brasil, 1000')
        ->and($community->address?->latitude)->toBe(-10.88)
        ->and($community->address?->longitude)->toBe(-61.95);
});
