<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('database seeder creates the configured or default system user', function () {
    putenv('APP_USER_SYSTEM_EMAIL');
    putenv('APP_USER_SYSTEM_PASSWORD');
    $expectedEmail = env('APP_USER_SYSTEM_EMAIL') ?: 'system@nossacasa.test';

    $this->seed(DatabaseSeeder::class);

    $superadmin = User::query()->where('email', $expectedEmail)->first();

    expect($superadmin)->not->toBeNull();
    expect($superadmin?->role)->toBe(UserRole::SYSTEM);
    expect($superadmin?->first_name)->toBe('System');
    expect($superadmin?->last_name)->toBe('Nossa Casa');

    expect(Church::query()->where('slug', 'nossa-casa-teste')->exists())->toBeTrue();
    expect(Community::query()->where('slug', 'nossa-comunidade-teste')->exists())->toBeTrue();
});
