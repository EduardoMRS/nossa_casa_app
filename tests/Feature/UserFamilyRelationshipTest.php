<?php

use App\Enums\UserRelationships;
use App\Models\User;
use App\Models\UserRelationship;

test('a user without a church can register a family member without contact information', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/settings/family-members', [
        'first_name' => 'Ana',
        'last_name' => 'Silva',
        'birth_date' => '2018-03-10',
        'relationship_type' => UserRelationships::PARENT->value,
    ]);

    $response->assertCreated();
    $relativeId = $response->json('id');

    expect(UserRelationship::query()
        ->where('user_id', $user->id)
        ->where('related_user_id', $relativeId)
        ->value('relationship_type'))
        ->toBe(UserRelationships::PARENT->value)
        ->and(User::query()->findOrFail($relativeId)->profile?->church_id)
        ->toBeNull();
});

test('a user without a church can link a family member by email', function () {
    $user = User::factory()->create();
    $relative = User::factory()->create();

    $this->actingAs($user)->postJson("/settings/relationships/{$user->id}", [
        'related_user_email' => $relative->email,
        'relationship_type' => UserRelationships::SPOUSE->value,
    ])->assertCreated();

    expect(UserRelationship::query()
        ->where('user_id', $user->id)
        ->where('related_user_id', $relative->id)
        ->value('relationship_type'))
        ->toBe(UserRelationships::SPOUSE->value)
        ->and(UserRelationship::query()
            ->where('user_id', $relative->id)
            ->where('related_user_id', $user->id)
            ->value('relationship_type'))
        ->toBe(UserRelationships::SPOUSE->value);
});
