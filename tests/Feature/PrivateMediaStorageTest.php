<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('media');
    config()->set('media.disk', 'media');
});

test('legacy public uploads can be moved to private storage', function () {
    Storage::disk('public')->put('church/example/logo.png', 'private-logo');

    $this->artisan('media:storage:migrate')
        ->expectsOutputToContain('Moved 1 file(s)')
        ->assertSuccessful();

    Storage::disk('public')->assertMissing('church/example/logo.png');
    Storage::disk('media')->assertExists('church/example/logo.png');
});

test('media optimize can force a specific already optimized media', function () {
    $uploader = User::factory()->create();
    $media = Media::query()->create([
        'uploader_id' => $uploader->id,
        'file_path' => 'church/example/document.pdf',
        'optimized_path' => 'previews/existing.webp',
        'mimetype' => 'application/pdf',
        'size' => 100,
    ]);

    $this->artisan('media:optimize', ['--force' => $media->id])
        ->expectsOutput('Mídias selecionadas para otimização: 1.')
        ->assertSuccessful();

    expect($media->refresh()->optimized_path)->toBe('previews/existing.webp');
});

test('media optimize requires an id when force is used', function () {
    $this->artisan('media:optimize', ['--force' => true])
        ->expectsOutput('Informe o ID da mídia com --force=ID.')
        ->assertFailed();
});

test('media optimize force all ignores the batch limit', function () {
    $uploader = User::factory()->create();

    foreach (['first', 'second'] as $name) {
        Media::query()->create([
            'uploader_id' => $uploader->id,
            'file_path' => "church/example/{$name}.pdf",
            'optimized_path' => "previews/{$name}.webp",
            'mimetype' => 'application/pdf',
            'size' => 100,
        ]);
    }

    $this->artisan('media:optimize', ['--force-all' => true, '--limit' => 1])
        ->expectsOutput('Mídias selecionadas para otimização: 2.')
        ->assertSuccessful();
});

test('private files are only served by encrypted temporary signed urls', function () {
    Storage::disk('media')->put('church/example/private.txt', 'private-content');

    $url = genUrl('church/example/private.txt');

    expect($url)->toContain('signature=')->toContain('expires=');

    $response = $this->get($url)->assertSuccessful();

    expect($response->baseResponse->getFile()->getContent())->toBe('private-content');

    $this->get($url.'&tampered=1')->assertForbidden();
});

test('internal S3 container endpoints stream files directly rather than redirecting to unreachable hosts', function () {
    config()->set('filesystems.disks.minio.driver', 's3');
    config()->set('filesystems.disks.minio.key', 'test-key');
    config()->set('filesystems.disks.minio.secret', 'test-secret');
    config()->set('filesystems.disks.minio.bucket', 'test-bucket');
    config()->set('filesystems.disks.minio.endpoint', 'http://minio:9000');
    config()->set('filesystems.disks.minio.temporary_url', null);

    Storage::fake('minio');
    Storage::disk('minio')->put('previews/sample.webp', 'image-binary-data');

    $url = genUrl('previews/sample.webp', 'minio');

    $response = $this->get($url);

    $response->assertOk();
});
