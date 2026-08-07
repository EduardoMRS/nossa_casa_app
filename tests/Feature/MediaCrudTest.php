<?php

use App\Enums\MediaStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('file metadata skips unavailable filesystem adapters', function () {
    config()->set('filesystems.disks', [
        'unavailable' => ['driver' => 'unavailable'],
    ]);

    $metadata = getFileMetadata('gallery/missing.jpg');

    expect($metadata['exists'])->toBeFalse()
        ->and($metadata['path'])->toBe('')
        ->and($metadata['handler'])->toBeNull();
});

test('file metadata resolves files from an available local disk', function () {
    Storage::disk('public')->put('gallery/sample.txt', 'sample');

    $metadata = getFileMetadata('gallery/sample.txt');

    expect($metadata['exists'])->toBeTrue()
        ->and($metadata['origin'])->toBe('local')
        ->and($metadata['size'])->toBe(6)
        ->and($metadata['name'])->toBe('sample.txt');
});

test('leader can upload media from a stored file path string', function () {
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($leader);

    $sourcePath = storage_path('app/testing-media.pdf');
    file_put_contents($sourcePath, '%PDF-1.4 test content');

    try {
        $response = $this->actingAs($leader)->postJson('/api/media', [
            'file_path' => $sourcePath,
            'gallery' => true,
        ]);

        $response->assertCreated();

        $media = Media::query()->latest()->firstOrFail();

        expect($media->church_id)->toBe($church->id);
        expect($media->status)->toBe(MediaStatus::PENDING);
        expect($media->file_path)->toStartWith('church/'.$church->id.'/media/');
        expect(Storage::disk('public')->exists($media->file_path))->toBeTrue();
    } finally {
        @unlink($sourcePath);
    }
});

test('member cannot upload media', function () {
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($member);

    $sourcePath = storage_path('app/testing-media-member.pdf');
    file_put_contents($sourcePath, '%PDF-1.4 test content');

    try {
        $this->actingAs($member)->postJson('/api/media', [
            'file_path' => $sourcePath,
            'gallery' => true,
        ])->assertForbidden();
    } finally {
        @unlink($sourcePath);
    }
});

test('admin can approve and delete pending media', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($admin);

    Storage::disk('public')->put('church/'.$church->id.'/media/sample.pdf', 'sample');

    $media = Media::query()->create([
        'church_id' => $church->id,
        'uploader_id' => $admin->id,
        'file_path' => 'church/'.$church->id.'/media/sample.pdf',
        'mimetype' => 'application/pdf',
        'size' => 6,
        'gallery' => true,
        'status' => MediaStatus::PENDING,
    ]);

    $this->actingAs($admin)->putJson('/api/admin/media/'.$media->id.'/status', [
        'status' => MediaStatus::APPROVED->value,
    ])->assertSuccessful();

    expect($media->refresh()->status)->toBe(MediaStatus::APPROVED);

    $this->actingAs($admin)->deleteJson('/api/media/'.$media->id)->assertNoContent();

    expect(Storage::disk('public')->exists('church/'.$church->id.'/media/sample.pdf'))->toBeFalse();
    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});
