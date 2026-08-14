<?php

use App\Enums\LiveStreamStatus;
use App\Enums\RecordingStatus;
use App\Enums\UserRole;
use App\Jobs\StoreRecordingInSharedStorage;
use App\Models\Church;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Recording;
use App\Models\User;
use App\Support\RecordingStoragePath;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('media.worker_token', 'test-worker-token');
    config()->set('media.worker_id', 'worker-test');
});

test('worker hooks require the shared secret', function () {
    $liveStream = LiveStream::factory()->create();

    $this->postJson('/api/internal/media/online', [
        'path' => $liveStream->path,
    ])->assertForbidden();
});

test('worker hooks update stream lifecycle', function () {
    $liveStream = LiveStream::factory()->create();
    $headers = ['X-Media-Worker-Token' => 'test-worker-token'];

    $this->withHeaders($headers)->postJson('/api/internal/media/online', [
        'path' => $liveStream->path,
        'source_type' => 'rtspSource',
        'source_id' => 'source-123',
        'worker_id' => 'worker-2',
    ])->assertNoContent();

    expect($liveStream->refresh())
        ->status->toBe(LiveStreamStatus::LIVE)
        ->worker_id->toBe('worker-2')
        ->started_at->not->toBeNull();

    $this->withHeaders($headers)->postJson('/api/internal/media/offline', [
        'path' => $liveStream->path,
    ])->assertNoContent();

    expect($liveStream->refresh())
        ->status->toBe(LiveStreamStatus::OFFLINE)
        ->ended_at->not->toBeNull();
});

test('completed segments are queued for shared archive storage', function () {
    Queue::fake();

    $recordingsRoot = storage_path('framework/testing/media-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    file_put_contents($segmentPath, 'recording-content');
    config()->set('media.recordings_root', $recordingsRoot);
    $liveStream = LiveStream::factory()->create();

    try {
        $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
            ->postJson('/api/internal/media/recording-segment-completed', [
                'path' => $liveStream->path,
                'segment_path' => $segmentPath,
                'segment_duration' => '15m0s',
            ])
            ->assertStatus(202);

        Queue::assertPushed(StoreRecordingInSharedStorage::class, function (StoreRecordingInSharedStorage $job) use ($liveStream, $segmentPath): bool {
            return $job->path === $liveStream->path
                && realpath($job->segmentPath) === realpath($segmentPath)
                && $job->size === 17;
        });
    } finally {
        if (file_exists($segmentPath)) {
            unlink($segmentPath);
        }

        if (is_dir($recordingsRoot)) {
            rmdir($recordingsRoot);
        }
    }
});

test('a stored recording is published in the transmissions media category', function () {
    Storage::fake('recordings');
    config()->set('media.archive_disk', 'recordings');
    config()->set('media.role', 'core');

    $church = Church::query()->create([
        'name' => 'Archive Church',
        'slug' => 'archive-church',
        'status' => 'active',
    ]);
    $creator = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($creator);
    $liveStream = LiveStream::factory()->create([
        'church_id' => $church->id,
        'created_by_id' => $creator->id,
    ]);
    $contents = 'recorded-service';
    $contentHash = hash('sha256', $contents);
    $deliveryId = hash('sha256', $liveStream->path."\0".$contentHash);
    $destination = RecordingStoragePath::forDelivery($deliveryId, 'service.mp4');
    Storage::disk('recordings')->put($destination, $contents);

    $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
        ->postJson('/api/internal/media/recording-stored', [
            'path' => $liveStream->path,
            'delivery_id' => $deliveryId,
            'content_hash' => $contentHash,
            'filename' => 'service.mp4',
            'mime_type' => 'video/mp4',
            'size' => strlen($contents),
            'duration' => '15m0s',
            'worker_id' => 'worker-test',
        ])
        ->assertNoContent();

    $recording = Recording::query()->firstOrFail();
    $media = Media::query()->firstOrFail();

    expect($recording->status)->toBe(RecordingStatus::READY)
        ->and($media)
        ->church_id->toBe($church->id)
        ->disk->toBe('recordings')
        ->gallery->toBeTrue()
        ->and($media->categories()->where('slug', 'transmissions')->exists())->toBeTrue()
        ->and($recording->media_id)->toBe($media->id);

    $this->get(route('gallery.download', $media))
        ->assertSuccessful()
        ->assertDownload(basename($media->file_path));
});

test('a private recording stays hidden until a media manager publishes it', function () {
    $this->withoutVite();
    Storage::fake('recordings');
    config()->set('media.archive_disk', 'recordings');
    config()->set('media.role', 'core');

    $church = Church::query()->create([
        'name' => 'Private Archive Church',
        'slug' => 'private-archive-church',
        'domain' => 'private-archive.test',
        'status' => 'active',
    ]);
    $creator = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($creator);
    $liveStream = LiveStream::factory()->create([
        'church_id' => $church->id,
        'created_by_id' => $creator->id,
        'is_public' => false,
    ]);
    $contents = 'private-recorded-service';
    $contentHash = hash('sha256', $contents);
    $deliveryId = hash('sha256', $liveStream->path."\0".$contentHash);
    $destination = RecordingStoragePath::forDelivery($deliveryId, 'private-service.mp4');
    Storage::disk('recordings')->put($destination, $contents);

    $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
        ->postJson('/api/internal/media/recording-stored', [
            'path' => $liveStream->path,
            'delivery_id' => $deliveryId,
            'content_hash' => $contentHash,
            'filename' => 'private-service.mp4',
            'mime_type' => 'video/mp4',
            'size' => strlen($contents),
            'worker_id' => 'worker-test',
        ])
        ->assertNoContent();

    $media = Media::query()->firstOrFail();

    expect($media->gallery)->toBeFalse();

    $this->get('http://private-archive.test/gallery?view=transmissions')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'transmissions')
            ->has('media.data', 0));
    $this->get('http://private-archive.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('latestRecordings', 0));
    $this->get("http://private-archive.test/gallery/{$media->id}/download")
        ->assertNotFound();
    $this->getJson('http://private-archive.test/api/media')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
    $this->getJson("http://private-archive.test/api/media/{$media->id}")
        ->assertNotFound();

    $this->actingAs($creator)
        ->putJson("/api/media/{$media->id}", ['gallery' => true])
        ->assertSuccessful()
        ->assertJsonPath('gallery', true);

    $this->get('http://private-archive.test/gallery?view=transmissions')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('media.data.0.id', $media->id));
    $this->get('http://private-archive.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestRecordings.0.id', $media->id));
    $this->getJson("http://private-archive.test/api/media/{$media->id}")
        ->assertSuccessful()
        ->assertJsonPath('id', $media->id);
});
