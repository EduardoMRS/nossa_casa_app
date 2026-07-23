<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('role:leader')->get('/role-probe', fn () => response()->json(['ok' => true]));
});

test('an administrator inherits leader permissions', function () {
    $administrator = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($administrator)
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
