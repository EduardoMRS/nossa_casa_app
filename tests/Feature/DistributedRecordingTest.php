<?php

use App\Enums\RecordingStatus;
use App\Jobs\StoreRecordingInSharedStorage;
use App\Models\LiveStream;
use App\Models\Recording;
use App\Support\RecordingStoragePath;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('media.worker_token', 'test-worker-token');
    config()->set('media.worker_id', 'remote-node-test');
});

test('a remote media node queues a completed recording for shared storage', function () {
    Queue::fake();
    config()->set('media.role', 'node');

    $recordingsRoot = storage_path('framework/testing/relay-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    file_put_contents($segmentPath, 'remote-recording');
    config()->set('media.recordings_root', $recordingsRoot);

    try {
        $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
            ->postJson('/api/internal/media/recording-segment-completed', [
                'path' => 'church-service',
                'segment_path' => $segmentPath,
                'segment_duration' => '15m0s',
            ])
            ->assertStatus(202);

        Queue::assertPushed(StoreRecordingInSharedStorage::class, function (StoreRecordingInSharedStorage $job) use ($segmentPath): bool {
            return realpath($job->segmentPath) === realpath($segmentPath)
                && $job->path === 'church-service'
                && $job->workerId === 'remote-node-test';
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

test('the core registers an authenticated recording already available in shared storage', function () {
    Storage::fake('recordings');
    config()->set('media.role', 'core');
    config()->set('media.archive_disk', 'recordings');

    $liveStream = LiveStream::factory()->create();
    $contents = 'recording-delivered-by-remote-node';
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
            'worker_id' => 'remote-node-1',
        ])
        ->assertNoContent();

    $recording = Recording::query()->sole();

    expect($recording)
        ->status->toBe(RecordingStatus::READY)
        ->worker_id->toBe('remote-node-1')
        ->disk->toBe('recordings')
        ->uploaded_at->not->toBeNull();

    Storage::disk('recordings')->assertExists($recording->path);

    $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
        ->postJson('/api/internal/media/recording-stored', [
            'path' => $liveStream->path,
            'delivery_id' => $deliveryId,
            'content_hash' => $contentHash,
            'filename' => 'service.mp4',
            'mime_type' => 'video/mp4',
            'size' => strlen($contents),
            'duration' => '15m0s',
            'worker_id' => 'remote-node-1',
        ])
        ->assertNoContent();

    expect(Recording::query()->count())->toBe(1);
});

test('the worker stores the segment directly and deletes its local copy only after core registration', function () {
    Storage::fake('recordings');
    Http::fake([
        'https://core.example.test/api/internal/media/recording-stored' => Http::response(status: 204),
    ]);
    config()->set('media.archive_disk', 'recordings');
    config()->set('media.core_url', 'https://core.example.test');
    config()->set('media.core_verify_tls', true);
    config()->set('media.core_connect_timeout', 1);
    config()->set('media.core_request_timeout', 10);

    $recordingsRoot = storage_path('framework/testing/relay-job-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    $contents = 'segment-ready-for-core';
    file_put_contents($segmentPath, $contents);
    $path = 'church-service';
    $contentHash = hash('sha256', $contents);
    $deliveryId = hash('sha256', $path."\0".$contentHash);
    $destination = RecordingStoragePath::forDelivery($deliveryId, 'segment.mp4');

    try {
        (new StoreRecordingInSharedStorage(
            path: $path,
            segmentPath: $segmentPath,
            deliveryId: $deliveryId,
            contentHash: $contentHash,
            filename: 'segment.mp4',
            mimeType: 'video/mp4',
            size: strlen($contents),
            duration: '15m0s',
            workerId: 'remote-node-test',
        ))->handle();

        expect(file_exists($segmentPath))->toBeFalse();
        Storage::disk('recordings')->assertExists($destination, $contents);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://core.example.test/api/internal/media/recording-stored'
            && $request->hasHeader('X-Media-Worker-Token', 'test-worker-token')
            && $request['content_hash'] === $contentHash
            && ! $request->isMultipart());
    } finally {
        if (file_exists($segmentPath)) {
            unlink($segmentPath);
        }

        if (is_dir($recordingsRoot)) {
            rmdir($recordingsRoot);
        }
    }
});

test('the worker keeps its local segment when core registration fails', function () {
    Storage::fake('recordings');
    Http::fake([
        'https://core.example.test/api/internal/media/recording-stored' => Http::response(status: 503),
    ]);
    config()->set('media.archive_disk', 'recordings');
    config()->set('media.core_url', 'https://core.example.test');

    $recordingsRoot = storage_path('framework/testing/relay-retry-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    $contents = 'segment-pending-core-registration';
    file_put_contents($segmentPath, $contents);
    $contentHash = hash('sha256', $contents);
    $deliveryId = hash('sha256', 'church-service'."\0".$contentHash);

    try {
        expect(fn () => (new StoreRecordingInSharedStorage(
            path: 'church-service',
            segmentPath: $segmentPath,
            deliveryId: $deliveryId,
            contentHash: $contentHash,
            filename: 'segment.mp4',
            mimeType: 'video/mp4',
            size: strlen($contents),
            duration: '15m0s',
            workerId: 'remote-node-test',
        ))->handle())->toThrow(RequestException::class);

        expect(file_exists($segmentPath))->toBeTrue();
        Storage::disk('recordings')->assertExists(
            RecordingStoragePath::forDelivery($deliveryId, 'segment.mp4'),
            $contents,
        );
    } finally {
        if (file_exists($segmentPath)) {
            unlink($segmentPath);
        }

        if (is_dir($recordingsRoot)) {
            rmdir($recordingsRoot);
        }
    }
});
