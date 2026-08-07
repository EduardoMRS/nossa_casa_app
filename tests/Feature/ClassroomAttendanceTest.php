<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\ClassroomPresence;
use App\Models\User;

test('a minor receives a one-time checkout pin and requires it for classroom checkout', function () {
    $church = Church::query()->create(['name' => 'Igreja Kids', 'slug' => 'igreja-kids']);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $child = User::factory()->create(['birth_date' => now()->subYears(8)->toDateString()]);
    $church->assignMember($leader);
    $church->assignMember($child);

    $classroom = Classroom::query()->create(['church_id' => $church->id, 'name' => 'Kids', 'min_age' => 5, 'max_age' => 10, 'is_kids' => true]);
    $classroom->members()->attach($child->id);

    $checkIn = $this->actingAs($leader)->postJson("/api/classrooms/{$classroom->id}/check-in", ['user_id' => $child->id]);
    $checkIn->assertSuccessful()->assertJsonPath('message', __('checkin.checkin_success'));
    $pin = $checkIn->json('checkout_pin');
    expect($pin)->toMatch('/^\d{6}$/')
        ->and(ClassroomPresence::query()->where('classroom_id', $classroom->id)->first()?->checkout_pin_code)->toBe($pin);

    $this->actingAs($leader)->postJson("/api/classrooms/{$classroom->id}/check-out", ['user_id' => $child->id, 'pin' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pin');

    $this->actingAs($leader)->postJson("/api/classrooms/{$classroom->id}/check-out", ['user_id' => $child->id, 'pin' => $pin])->assertSuccessful();

    $presence = ClassroomPresence::query()->where('classroom_id', $classroom->id)->whereNotNull('check_out')->first();
    expect($presence)->not->toBeNull()
        ->and($presence?->checkout_pin_code)->toBeNull();
});
