<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\ChurchStorageSetting;
use App\Models\Community;
use App\Models\Network;
use App\Models\User;
use App\Support\ChurchStorageManager;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('church storage credentials are encrypted at rest and can be updated by church leader', function () {
    $community = Community::factory()->create();
    $church = Church::factory()->for($community)->create([
        'domain' => 'storage-test.localhost',
    ]);
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    $response = $this->actingAs($leader)->put('/dashboard/configuracoes-church', [
        'domain' => 'storage-test.localhost',
        'storage' => [
            'enabled' => true,
            'allow_branches' => true,
            'driver' => 's3',
            'config' => [
                'bucket' => 'my-church-bucket',
                'region' => 'us-east-1',
                'endpoint' => 'https://s3.example.com',
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'use_path_style_endpoint' => true,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    // Check encrypted at rest in raw DB
    $raw = DB::table('church_storage_settings')->where('church_id', $church->id)->first();
    expect($raw)->not->toBeNull()
        ->and($raw->config)->not->toContain('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');

    // Check decrypted via Eloquent model
    $setting = ChurchStorageSetting::query()->where('church_id', $church->id)->first();
    expect($setting->enabled)->toBeTrue()
        ->and($setting->allow_branches)->toBeTrue()
        ->and($setting->driver)->toBe('s3')
        ->and($setting->config['secret'])->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY')
        ->and($setting->config['bucket'])->toBe('my-church-bucket');

    // Check publicConfig masks secrets
    $publicConfig = $setting->publicConfig();
    expect($publicConfig)->not->toHaveKey('secret')
        ->and($publicConfig['has_secret'])->toBeTrue()
        ->and($publicConfig['bucket'])->toBe('my-church-bucket');
});

test('updating storage settings without re-entering secret preserves existing secret', function () {
    $church = Church::factory()->create(['domain' => 'preserve-secret.localhost']);
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    ChurchStorageSetting::query()->create([
        'church_id' => $church->id,
        'enabled' => true,
        'driver' => 's3',
        'config' => [
            'bucket' => 'initial-bucket',
            'key' => 'initial-key',
            'secret' => 'top-secret-value-123',
        ],
    ]);

    // Send update with empty secret
    $this->actingAs($leader)->put('/dashboard/configuracoes-church', [
        'domain' => 'preserve-secret.localhost',
        'storage' => [
            'enabled' => true,
            'driver' => 's3',
            'config' => [
                'bucket' => 'updated-bucket',
                'key' => 'initial-key',
                'secret' => '',
            ],
        ],
    ])->assertSessionHasNoErrors();

    $setting = ChurchStorageSetting::query()->where('church_id', $church->id)->first();
    expect($setting->config['bucket'])->toBe('updated-bucket')
        ->and($setting->config['secret'])->toBe('top-secret-value-123');
});

test('child church inherits storage setting from headquarters when allow_branches is true', function () {
    $parent = Church::factory()->create();
    $child = Church::factory()->create();

    Network::query()->create([
        'parent_church_id' => $parent->id,
        'child_church_id' => $child->id,
    ]);

    ChurchStorageSetting::query()->create([
        'church_id' => $parent->id,
        'enabled' => true,
        'allow_branches' => true,
        'driver' => 's3',
        'config' => [
            'bucket' => 'parent-network-bucket',
            'key' => 'parent-key',
            'secret' => 'parent-secret',
        ],
    ]);

    $manager = app(ChurchStorageManager::class);
    $resolved = $manager->resolveFor($child);

    expect($resolved)->not->toBeNull()
        ->and($resolved['is_inherited'])->toBeTrue()
        ->and($resolved['setting']->church_id)->toBe($parent->id)
        ->and($resolved['setting']->config['bucket'])->toBe('parent-network-bucket')
        ->and($manager->diskNameFor($child))->toBe('church_'.$parent->id);
});

test('child church with own active storage takes precedence over inherited storage', function () {
    $parent = Church::factory()->create();
    $child = Church::factory()->create();

    Network::query()->create([
        'parent_church_id' => $parent->id,
        'child_church_id' => $child->id,
    ]);

    ChurchStorageSetting::query()->create([
        'church_id' => $parent->id,
        'enabled' => true,
        'allow_branches' => true,
        'driver' => 's3',
        'config' => ['bucket' => 'parent-bucket'],
    ]);

    ChurchStorageSetting::query()->create([
        'church_id' => $child->id,
        'enabled' => true,
        'allow_branches' => false,
        'driver' => 'sftp',
        'config' => ['host' => 'child-sftp.test'],
    ]);

    $manager = app(ChurchStorageManager::class);
    $resolved = $manager->resolveFor($child);

    expect($resolved)->not->toBeNull()
        ->and($resolved['is_inherited'])->toBeFalse()
        ->and($resolved['setting']->church_id)->toBe($child->id)
        ->and($resolved['setting']->driver)->toBe('sftp')
        ->and($manager->diskNameFor($child))->toBe('church_'.$child->id);
});

test('test storage connection endpoint returns success for default platform driver', function () {
    $church = Church::factory()->create(['domain' => 'test-conn.localhost']);
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    $response = $this->actingAs($leader)->postJson('/dashboard/church-settings/test-storage', [
        'driver' => 'default',
        'config' => [],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);
});

test('test storage connection endpoint fails with 422 when required credentials are missing', function () {
    $church = Church::factory()->create(['domain' => 'test-fail.localhost']);
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);

    $response = $this->actingAs($leader)->postJson('/dashboard/church-settings/test-storage', [
        'driver' => 's3',
        'config' => [
            'bucket' => '',
            'key' => '',
            'secret' => '',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('storage quota calculation and warning respects target roles and limit', function () {
    $church = Church::factory()->create();
    $mediaUser = User::factory()->create(['role' => UserRole::MEDIA]);
    $leaderUser = User::factory()->create(['role' => UserRole::LEADER]);
    $pastorUser = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $superadminUser = User::factory()->create(['role' => UserRole::SUPERADMIN]);
    $systemUser = User::factory()->create(['role' => UserRole::SYSTEM]);
    $memberUser = User::factory()->create(['role' => UserRole::MEMBER]);
    $guestUser = User::factory()->create(['role' => UserRole::GUEST]);

    // Set custom limit for test: 1 MB (1048576 bytes)
    config(['filesystems.church_storage_limit_bytes' => 1048576]);

    // Create media totaling 2 MB (2097152 bytes)
    \App\Models\Media::query()->create([
        'church_id' => $church->id,
        'uploader_id' => $mediaUser->id,
        'title' => 'Large file',
        'file_path' => 'test/file.mp4',
        'size' => 2097152,
        'status' => \App\Enums\MediaStatus::APPROVED,
    ]);

    $manager = app(ChurchStorageManager::class);

    // Platform default storage: exceeded!
    $summaryMedia = $manager->storageQuotaSummary($church, $mediaUser);
    expect($summaryMedia['is_custom'])->toBeFalse()
        ->and($summaryMedia['is_exceeded'])->toBeTrue()
        ->and($summaryMedia['should_show_warning'])->toBeTrue()
        ->and($summaryMedia['used_bytes'])->toBe(2097152);

    expect($manager->storageQuotaSummary($church, $leaderUser)['should_show_warning'])->toBeTrue()
        ->and($manager->storageQuotaSummary($church, $pastorUser)['should_show_warning'])->toBeTrue()
        ->and($manager->storageQuotaSummary($church, $superadminUser)['should_show_warning'])->toBeTrue()
        ->and($manager->storageQuotaSummary($church, $systemUser)['should_show_warning'])->toBeTrue();

    // Member and Guest must NOT receive warning
    expect($manager->storageQuotaSummary($church, $memberUser)['should_show_warning'])->toBeFalse()
        ->and($manager->storageQuotaSummary($church, $guestUser)['should_show_warning'])->toBeFalse();

    // If church has custom storage enabled, warning must NOT be shown
    ChurchStorageSetting::query()->create([
        'church_id' => $church->id,
        'enabled' => true,
        'driver' => 's3',
        'config' => ['bucket' => 'custom-bucket'],
    ]);

    $customSummary = $manager->storageQuotaSummary($church, $pastorUser);
    expect($customSummary['is_custom'])->toBeTrue()
        ->and($customSummary['is_exceeded'])->toBeFalse()
        ->and($customSummary['should_show_warning'])->toBeFalse();
});

test('migrate church storage job copies files directly and creates audit record', function () {
    \Illuminate\Support\Facades\Storage::fake('media');
    \Illuminate\Support\Facades\Storage::fake('church_custom');

    $church = Church::factory()->create();
    $sourceDisk = \Illuminate\Support\Facades\Storage::disk('media');
    $sourceDisk->put('church/'.$church->id.'/media/sample.jpg', 'fake-image-binary-data');

    $media = \App\Models\Media::query()->create([
        'church_id' => $church->id,
        'uploader_id' => User::factory()->create()->id,
        'title' => 'Sample Image',
        'file_path' => 'church/'.$church->id.'/media/sample.jpg',
        'size' => 22,
        'status' => \App\Enums\MediaStatus::APPROVED,
        'disk' => 'media',
    ]);

    ChurchStorageSetting::query()->create([
        'church_id' => $church->id,
        'enabled' => true,
        'driver' => 's3',
        'config' => ['bucket' => 'target-bucket'],
    ]);

    $job = new \App\Jobs\MigrateChurchStorageJob($church->id, 'default');
    $job->handle(app(ChurchStorageManager::class));

    // Audit record created with 3-day retention window
    $backup = \App\Models\ChurchStorageBackup::query()->where('church_id', $church->id)->first();
    expect($backup)->not->toBeNull()
        ->and($backup->status)->toBe('completed')
        ->and($backup->backup_directory)->toBe('direct_migration')
        ->and($backup->cleanup_at)->toBeGreaterThan(now()->addDays(2))
        ->and($backup->cleanup_at)->toBeLessThanOrEqual(now()->addDays(4));

    // Source file remains intact (no deletion)
    expect($sourceDisk->exists('church/'.$church->id.'/media/sample.jpg'))->toBeTrue();

    // Media model updated to point to target disk
    expect($media->fresh()->disk)->toBe('church_'.$church->id);
});

test('cleanup storage backups command deletes expired backups and local folders', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $localDisk = \Illuminate\Support\Facades\Storage::disk('local');

    $church = Church::factory()->create();

    // Expired backup (older than 3 days)
    $expiredDir = 'storage_backups/expired_backup';
    $localDisk->put($expiredDir.'/test.txt', 'expired data');
    $expiredBackup = \App\Models\ChurchStorageBackup::query()->create([
        'church_id' => $church->id,
        'source_driver' => 'default',
        'target_driver' => 's3',
        'backup_disk' => 'local',
        'backup_directory' => $expiredDir,
        'status' => 'completed',
        'cleanup_at' => now()->subHour(),
    ]);

    // Active backup (valid for next 2 days)
    $activeDir = 'storage_backups/active_backup';
    $localDisk->put($activeDir.'/test.txt', 'active data');
    $activeBackup = \App\Models\ChurchStorageBackup::query()->create([
        'church_id' => $church->id,
        'source_driver' => 'default',
        'target_driver' => 's3',
        'backup_disk' => 'local',
        'backup_directory' => $activeDir,
        'status' => 'completed',
        'cleanup_at' => now()->addDays(2),
    ]);

    \Illuminate\Support\Facades\Artisan::call('church:cleanup-storage-backups');

    // Expired backup deleted
    expect(\App\Models\ChurchStorageBackup::query()->find($expiredBackup->id))->toBeNull()
        ->and($localDisk->exists($expiredDir))->toBeFalse();

    // Active backup still preserved
    expect(\App\Models\ChurchStorageBackup::query()->find($activeBackup->id))->not->toBeNull()
        ->and($localDisk->exists($activeDir.'/test.txt'))->toBeTrue();
});
