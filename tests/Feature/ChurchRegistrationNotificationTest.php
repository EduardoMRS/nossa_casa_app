<?php

use App\Enums\UserRole;
use App\Mail\ChurchRegistrationRequestedMail;
use App\Models\Church;
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
