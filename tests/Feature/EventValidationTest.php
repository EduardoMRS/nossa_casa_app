<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;

test('event store rejects end time before start time', function () {
    $church = Church::create([
        'name' => 'Igreja Central',
        'slug' => 'igreja-central',
    ]);

    $leader = User::factory()->create([
        'role' => UserRole::LEADER,
    ]);

    $leader->profile()->create([
        'church_id' => $church->id,
    ]);

    $response = $this->actingAs($leader)->postJson('/api/event', [
        'title' => 'Retiro de Jovens',
        'slug' => 'retiro-jovens',
        'start_time' => now()->addDay()->toIso8601String(),
        'end_time' => now()->subDay()->toIso8601String(),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['end_time']);
});
