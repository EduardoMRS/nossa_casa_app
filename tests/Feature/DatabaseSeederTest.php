<?php

use App\Enums\UserRole;
use App\Models\AiModel;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InitialCommunitySeeder;
use Illuminate\Support\Facades\Storage;

test('database seeder includes development data outside production', function () {
    Storage::fake((string) config('media.disk'));
    config()->set('app.system_user.email', 'system@nossacasa.test');
    config()->set('app.system_user.password', 'password');

    $this->seed(DatabaseSeeder::class);

    $systemUser = User::query()->where('email', 'system@nossacasa.test')->firstOrFail();

    expect($systemUser->role)->toBe(UserRole::SYSTEM)
        ->and($systemUser->first_name)->toBe('Sistema')
        ->and($systemUser->last_name)->toBe('Nossa Casa')
        ->and(Community::query()->where('slug', 'protestante')->exists())->toBeTrue()
        ->and(Church::query()->where('slug', 'assembleia-de-deus-machadinho-doeste')->exists())->toBeTrue()
        ->and(Church::query()->where('slug', 'primeira-igreja-batista-ji-parana')->exists())->toBeTrue()
        ->and(Community::query()->where('slug', 'comunidade-crista-de-rondonia')->exists())->toBeTrue()
        ->and(AiModel::query()->where('model_id', 'inclusionai/ling-3.0-flash:free')->exists())->toBeTrue();
});

test('database seeder excludes development data in production', function () {
    Storage::fake((string) config('media.disk'));
    config()->set('app.system_user.email', 'system@nossacasa.test');
    config()->set('app.system_user.password', 'password');

    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ])->assertSuccessful();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }

    expect(User::query()->where('email', 'system@nossacasa.test')->where('role', UserRole::SYSTEM)->exists())->toBeTrue()
        ->and(Church::query()->exists())->toBeFalse()
        ->and(Community::query()->where('slug', 'protestante')->exists())->toBeTrue()
        ->and(Community::query()->count())->toBe(4)
        ->and(AiModel::query()->where('model_id', 'inclusionai/ling-3.0-flash:free')->exists())->toBeTrue();
});

test('initial communities are idempotent and preserve later edits', function () {
    $this->seed(InitialCommunitySeeder::class);
    Community::query()->where('slug', 'protestante')->update([
        'description' => 'Descrição personalizada.',
    ]);

    $this->seed(InitialCommunitySeeder::class);

    expect(Community::query()->count())->toBe(4)
        ->and(Community::query()->where('slug', 'protestante')->value('description'))
        ->toBe('Descrição personalizada.');
});
