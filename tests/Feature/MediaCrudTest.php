<?php

use App\Enums\MediaStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('media');
});

test('file metadata skips unavailable filesystem adapters', function () {
    config()->set('filesystems.disks', [
        'unavailable' => ['driver' => 'unavailable'],
    ]);
    config()->set('filesystems.metadata_disks', ['unavailable']);

    $metadata = getFileMetadata('gallery/missing.jpg');

    expect($metadata['exists'])->toBeFalse()
        ->and($metadata['path'])->toBe('')
        ->and($metadata['handler'])->toBeNull();
});

test('file metadata resolves files from an available local disk', function () {
    config()->set('filesystems.metadata_disks', ['public']);
    Storage::disk('public')->put('gallery/sample.txt', 'sample');

    $metadata = getFileMetadata('gallery/sample.txt');

    expect($metadata['exists'])->toBeTrue()
        ->and($metadata['origin'])->toBe('local')
        ->and($metadata['size'])->toBe(6)
        ->and($metadata['name'])->toBe('sample.txt');
});

test('file metadata ignores disks that are not configured for metadata lookup', function () {
    Storage::disk('public')->put('gallery/not-searchable.txt', 'sample');
    config()->set('filesystems.metadata_disks', ['media']);

    $metadata = getFileMetadata('gallery/not-searchable.txt');

    expect($metadata['exists'])->toBeFalse();
});

test('stored files are served only through encrypted temporary signed urls', function () {
    Storage::disk('media')->put('gallery/private.txt', 'private content');

    $url = genUrl('gallery/private.txt');

    expect($url)->toContain('signature=')
        ->and($url)->toContain('expires=');

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="private.txt"');

    $this->get($url.'&tampered=1')->assertForbidden();
});

test('leader can upload media from a stored file path string', function () {
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($leader);

    $sourcePath = storage_path('app/testing-media.pdf');
    file_put_contents($sourcePath, '%PDF-1.4 test content');

    try {
        $response = $this->actingAs($leader)->postJson('/api/media', [
            'title' => 'Leadership handbook',
            'description' => 'A resource shared with the gallery.',
            'file_path' => $sourcePath,
            'gallery' => true,
        ]);

        $response->assertCreated();

        $media = Media::query()->latest()->firstOrFail();

        expect($media->church_id)->toBe($church->id);
        expect($media->title)->toBe('Leadership handbook');
        expect($media->description)->toBe('A resource shared with the gallery.');
        expect($media->status)->toBe(MediaStatus::PENDING);
        expect($media->file_path)->toStartWith('church/'.$church->id.'/media/');
        expect(Storage::disk('media')->exists($media->file_path))->toBeTrue();
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

    Storage::disk('media')->put('church/'.$church->id.'/media/sample.pdf', 'sample');

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

    expect(Storage::disk('media')->exists('church/'.$church->id.'/media/sample.pdf'))->toBeFalse();
    expect(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});

test('registered media source cannot be replaced while record data can be edited', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($admin);

    $originalPath = 'church/'.$church->id.'/media/original.jpg';
    Storage::disk('media')->put($originalPath, 'original-media');

    $media = Media::query()->create([
        'church_id' => $church->id,
        'uploader_id' => $admin->id,
        'title' => 'Original title',
        'description' => 'Original description',
        'file_path' => $originalPath,
        'mimetype' => 'image/jpeg',
        'size' => 14,
        'gallery' => true,
        'status' => MediaStatus::PENDING,
    ]);

    $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->put('/api/media/'.$media->id, [
            'file_path' => 'https://example.test/replacement.jpg',
            'file' => UploadedFile::fake()->create('replacement.jpg', 10, 'image/jpeg'),
            'mimetype' => 'image/png',
            'size' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file_path', 'file', 'mimetype', 'size']);

    expect($media->refresh())
        ->file_path->toBe($originalPath)
        ->mimetype->toBe('image/jpeg')
        ->size->toBe(14);

    $this->actingAs($admin)->putJson('/api/media/'.$media->id, [
        'title' => 'Updated title',
        'description' => 'Updated description',
        'gallery' => false,
    ])->assertSuccessful();

    expect($media->refresh())
        ->title->toBe('Updated title')
        ->description->toBe('Updated description')
        ->gallery->toBeFalse()
        ->file_path->toBe($originalPath)
        ->mimetype->toBe('image/jpeg')
        ->size->toBe(14);
    expect(Storage::disk('media')->exists($originalPath))->toBeTrue();
});
