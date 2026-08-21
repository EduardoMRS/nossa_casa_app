<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('members cannot visit the dashboard directly', function () {
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    Church::factory()->create()->assignMember($user);

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});

test('roles above member can visit the dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::LEADER]);
    Church::factory()->create()->assignMember($user);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
