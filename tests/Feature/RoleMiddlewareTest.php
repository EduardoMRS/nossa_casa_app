<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('role:leader')->get('/role-probe', fn () => response()->json(['ok' => true]));
});

test('a church leader inherits team leader permissions', function () {
    $churchLeader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);

    $this->actingAs($churchLeader)
        ->getJson('/role-probe')
        ->assertSuccessful()
        ->assertJson(['ok' => true]);
});

test('a member cannot use leader endpoints', function () {
    $member = User::factory()->create(['role' => UserRole::MEMBER]);

    $this->actingAs($member)
        ->getJson('/role-probe')
        ->assertForbidden();
});
