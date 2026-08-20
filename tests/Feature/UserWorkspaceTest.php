<?php

use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Community;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('profile workspace contains the members personal modules', function () {
    $this->withoutVite();
    $church = Church::query()->create(['name' => 'Member Church', 'slug' => 'member-church']);
    $member = User::factory()->create();
    $child = User::factory()->create(['birth_date' => now()->subYears(8)->toDateString()]);
    $church->assignMember($member);
    $church->assignMember($child);
    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $member->id,
        'title' => 'Member event',
        'slug' => 'member-event',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
    ]);
    $event->users()->attach($member->id, ['status' => 'approved']);
    $classroom = Classroom::query()->create(['church_id' => $church->id, 'name' => 'Family room', 'is_kids' => true]);
    $classroom->members()->attach($member->id);
    PrayerRequest::query()->create(['church_id' => $church->id, 'user_id' => $member->id, 'content' => 'My own request']);

    $this->actingAs($member)->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Workspace')
            ->where('workspaceUser.id', $member->id)
            ->where('workspaceUser.registered_events.0.id', $event->id)
            ->where('workspaceUser.classrooms.0.id', $classroom->id)
            ->where('prayerRequests.0.content', 'My own request'));
});

test('adding a child creates the reciprocal guardian relationship', function () {
    $church = Church::query()->create(['name' => 'Family Church', 'slug' => 'family-church']);
    $guardian = User::factory()->create();
    $child = User::factory()->create(['birth_date' => now()->subYears(7)->toDateString()]);
    $church->assignMember($guardian);
    $church->assignMember($child);

    $this->actingAs($guardian)->postJson(route('profile.relationships.store', ['user' => $guardian->id]), [
        'related_user_id' => $child->id,
        'relationship_type' => UserRelationships::PARENT->value,
    ])->assertCreated();

    $this->assertDatabaseHas('user_relationships', [
        'user_id' => $guardian->id,
        'related_user_id' => $child->id,
        'relationship_type' => UserRelationships::PARENT->value,
    ]);
    $this->assertDatabaseHas('user_relationships', [
        'user_id' => $child->id,
        'related_user_id' => $guardian->id,
        'relationship_type' => UserRelationships::CHILD->value,
    ]);
});

test('member can update user and profile fields including avatar', function () {
    Storage::fake('public');
    Storage::fake('media');
    $user = User::factory()->create();
    $community = Community::query()->create([
        'name' => 'Selected Community',
        'slug' => 'selected-community',
        'description' => 'Community used to validate profile membership selection.',
    ]);
    $church = Church::query()->create(['name' => 'Selected Church', 'slug' => 'selected-church', 'community_id' => $community->id]);

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'patch',
        'name' => 'Updated Member',
        'email' => $user->email,
        'birth_date' => '1990-05-10',
        'phone' => '+55 92 99999-0000',
        'gender' => 'female',
        'community_id' => $community->id,
        'church_id' => $church->id,
        'avatar' => UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg'),
    ])->assertRedirect(route('profile.edit'));

    $user->refresh()->load('profile');

    expect($user->name)->toBe('Updated Member')
        ->and($user->birth_date?->toDateString())->toBe('1990-05-10')
        ->and($user->profile?->phone)->toBe('+55 92 99999-0000')
        ->and($user->profile?->gender)->toBe('female')
        ->and($user->profile?->community_id)->toBe($community->id)
        ->and($user->profile?->church_id)->toBe($church->id);
    Storage::disk('media')->assertExists($user->profile->avatar_path);
});

test('profile church selection requires its matching community', function () {
    $user = User::factory()->create();
    $community = Community::factory()->create();
    $otherCommunity = Community::factory()->create();
    $church = Church::factory()->create(['community_id' => $community->id]);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'church_id' => $church->id,
    ])->assertSessionHasErrors('church_id');

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'community_id' => $otherCommunity->id,
        'church_id' => $church->id,
    ])->assertSessionHasErrors('church_id');

    expect($user->fresh()->profile?->church_id)->toBeNull();
});

test('guardian can register a child with care notes', function () {
    $community = Community::query()->create([
        'name' => 'Family Community',
        'slug' => 'family-community',
        'description' => 'Community used to validate family management.',
    ]);
    $church = Church::query()->create(['name' => 'Family Church Child', 'slug' => 'family-church-child', 'community_id' => $community->id]);
    $guardian = User::factory()->create();
    $guardian->profile()->create(['church_id' => $church->id, 'community_id' => $community->id]);

    $response = $this->actingAs($guardian)->postJson(route('profile.children.store'), [
        'first_name' => 'Little',
        'last_name' => 'Member',
        'birth_date' => now()->subYears(7)->toDateString(),
        'gender' => 'female',
        'medical_notes' => 'Allergic to peanuts.',
    ])->assertCreated();

    $childId = $response->json('id');
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $childId,
        'church_id' => $church->id,
        'community_id' => $community->id,
        'medical_notes' => 'Allergic to peanuts.',
    ]);
    $this->assertDatabaseHas('user_relationships', [
        'user_id' => $guardian->id,
        'related_user_id' => $childId,
        'relationship_type' => UserRelationships::PARENT->value,
    ]);
});

test('guardian sees the encrypted pickup pin for a checked in child', function () {
    $this->withoutVite();
    $church = Church::query()->create(['name' => 'Secure Kids Church', 'slug' => 'secure-kids-church']);
    $guardian = User::factory()->create(['role' => UserRole::LEADER]);
    $child = User::factory()->create(['birth_date' => now()->subYears(8)->toDateString()]);
    $church->assignMember($guardian);
    $church->assignMember($child);
    $guardian->relationships()->create([
        'related_user_id' => $child->id,
        'relationship_type' => UserRelationships::PARENT->value,
    ]);
    $classroom = Classroom::query()->create([
        'church_id' => $church->id,
        'name' => 'Kids Secure Room',
        'min_age' => 5,
        'max_age' => 10,
        'is_kids' => true,
    ]);
    $classroom->members()->attach($child->id);

    $pin = $this->actingAs($guardian)
        ->postJson("/api/classrooms/{$classroom->id}/check-in", [
            'user_id' => $child->id,
            'guardian_user_id' => $guardian->id,
        ])
        ->assertSuccessful()
        ->json('checkout_pin');

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingChildCheckouts.0.user_id', $child->id)
            ->where('pendingChildCheckouts.0.checkout_pin', $pin)
            ->where('pendingChildCheckouts.0.classroom_name', 'Kids Secure Room'));
});

test('member can create an identified intercession request from the workspace', function () {
    $church = Church::query()->create(['name' => 'Prayer Church', 'slug' => 'prayer-church-workspace']);
    $member = User::factory()->create();
    $church->assignMember($member);

    $this->actingAs($member)->postJson('/api/prayer-requests', [
        'content' => 'Please pray for my family.',
        'is_anonymous' => false,
    ])->assertCreated();

    $this->assertDatabaseHas('prayer_requests', [
        'user_id' => $member->id,
        'church_id' => $church->id,
        'content' => 'Please pray for my family.',
    ]);
});
