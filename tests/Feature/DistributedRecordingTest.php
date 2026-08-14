<?php

use App\Enums\RecordingStatus;
use App\Jobs\RelayRecordingToCore;
use App\Models\LiveStream;
use App\Models\Recording;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('media.worker_token', 'test-worker-token');
    config()->set('media.worker_id', 'remote-node-test');
});

test('a remote media node queues a completed recording for delivery', function () {
    Queue::fake();
    config()->set('media.role', 'relay');

    $recordingsRoot = storage_path('framework/testing/relay-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    file_put_contents($segmentPath, 'remote-recording');
    config()->set('media.recordings_root', $recordingsRoot);

    try {
        $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
            ->postJson('/api/internal/media/relay-recording', [
                'path' => 'church-service',
                'segment_path' => $segmentPath,
                'segment_duration' => '15m0s',
            ])
            ->assertStatus(202);

        Queue::assertPushed(RelayRecordingToCore::class, function (RelayRecordingToCore $job) use ($segmentPath): bool {
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

test('the core accepts an authenticated recording and publishes it to the archive', function () {
    Storage::fake('recordings');
    config()->set('media.role', 'core');
    config()->set('media.archive_disk', 'recordings');

    $liveStream = LiveStream::factory()->create();
    $contents = 'recording-delivered-by-remote-node';
    $deliveryId = hash('sha256', $liveStream->path."\0".hash('sha256', $contents));

    $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
        ->post('/api/internal/media/recording-ingest', [
            'path' => $liveStream->path,
            'delivery_id' => $deliveryId,
            'filename' => 'service.mp4',
            'duration' => '15m0s',
            'worker_id' => 'remote-node-1',
            'file' => UploadedFile::fake()->createWithContent('service.mp4', $contents),
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
        ->post('/api/internal/media/recording-ingest', [
            'path' => $liveStream->path,
            'delivery_id' => $deliveryId,
            'filename' => 'service.mp4',
            'duration' => '15m0s',
            'worker_id' => 'remote-node-1',
            'file' => UploadedFile::fake()->createWithContent('service.mp4', $contents),
        ])
        ->assertNoContent();

    expect(Recording::query()->count())->toBe(1);
});

test('the relay deletes its local segment only after the core accepts it', function () {
    Http::fake([
        'https://core.example.test/api/internal/media/recording-ingest' => Http::response(status: 204),
    ]);
    config()->set('media.core_url', 'https://core.example.test');
    config()->set('media.core_verify_tls', true);
    config()->set('media.core_connect_timeout', 1);
    config()->set('media.core_upload_timeout', 10);

    $recordingsRoot = storage_path('framework/testing/relay-job-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    $contents = 'segment-ready-for-core';
    file_put_contents($segmentPath, $contents);
    $path = 'church-service';
    $deliveryId = hash('sha256', $path."\0".hash('sha256', $contents));

    try {
        (new RelayRecordingToCore(
            path: $path,
            segmentPath: $segmentPath,
            deliveryId: $deliveryId,
            filename: 'segment.mp4',
            duration: '15m0s',
            workerId: 'remote-node-test',
        ))->handle();

        expect(file_exists($segmentPath))->toBeFalse();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://core.example.test/api/internal/media/recording-ingest'
            && $request->hasHeader('X-Media-Worker-Token', 'test-worker-token'));
    } finally {
        if (file_exists($segmentPath)) {
            unlink($segmentPath);
        }

        if (is_dir($recordingsRoot)) {
            rmdir($recordingsRoot);
        }
    }
});
