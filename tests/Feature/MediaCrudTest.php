<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;

test('a member upload is always linked to the authenticated user and church', function () {
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa']);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $member->profile()->create(['church_id' => $church->id]);

    $response = $this->actingAs($member)
        ->postJson('/api/media', [
            'file_path' => 'gallery/photo.jpg',
            'mimetype' => 'image/jpeg',
            'size' => 1024,
            'gallery' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('uploader_id', $member->id)
        ->assertJsonPath('church_id', $church->id)
        ->assertJsonPath('status', 'pending');
});
