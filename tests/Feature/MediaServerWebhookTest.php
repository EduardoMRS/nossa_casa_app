<?php

use App\Actions\Media\FinalizeRecording;
use App\Enums\LiveStreamStatus;
use App\Enums\RecordingStatus;
use App\Enums\UserRole;
use App\Jobs\UploadRecording;
use App\Models\Church;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

test('completed segments are queued once and uploaded to private archive storage', function () {
    Queue::fake();
    Storage::fake('recordings');

    $recordingsRoot = storage_path('framework/testing/media-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'segment.mp4';
    file_put_contents($segmentPath, 'recording-content');
    config()->set('media.recordings_root', $recordingsRoot);
    config()->set('media.archive_disk', 'recordings');

    $liveStream = LiveStream::factory()->create();

    try {
        $this->withHeader('X-Media-Worker-Token', 'test-worker-token')
            ->postJson('/api/internal/media/recording-completed', [
                'path' => $liveStream->path,
                'segment_path' => $segmentPath,
                'segment_duration' => '15m0s',
            ])
            ->assertNoContent();

        $recording = Recording::query()->firstOrFail();

        expect($recording)
            ->status->toBe(RecordingStatus::WAITING_UPLOAD)
            ->size->toBe(17);

        Queue::assertPushed(UploadRecording::class, fn (UploadRecording $job): bool => $job->recordingId === $recording->id);

        (new UploadRecording($recording->id))->handle(app(FinalizeRecording::class));

        $recording->refresh();

        expect($recording)
            ->status->toBe(RecordingStatus::READY)
            ->disk->toBe('recordings')
            ->uploaded_at->not->toBeNull()
            ->and(file_exists($segmentPath))->toBeFalse();
        Storage::disk('recordings')->assertExists($recording->path);
    } finally {
        if (file_exists($segmentPath)) {
            unlink($segmentPath);
        }

        if (is_dir($recordingsRoot)) {
            rmdir($recordingsRoot);
        }
    }
});

test('an uploaded recording is published in the transmissions media category', function () {
    Storage::fake('recordings');
    $recordingsRoot = storage_path('framework/testing/media-'.Str::lower((string) Str::ulid()));
    mkdir($recordingsRoot, 0777, true);
    $segmentPath = $recordingsRoot.DIRECTORY_SEPARATOR.'service.mp4';
    file_put_contents($segmentPath, 'recorded-service');
    config()->set('media.recordings_root', $recordingsRoot);
    config()->set('media.archive_disk', 'recordings');

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
    $recording = Recording::factory()->create([
        'live_stream_id' => $liveStream->id,
        'worker_path' => $segmentPath,
        'worker_path_hash' => hash('sha256', $segmentPath),
        'filename' => 'service.mp4',
        'mime_type' => 'video/mp4',
        'size' => 16,
    ]);

    try {
        (new UploadRecording($recording->id))->handle(app(FinalizeRecording::class));

        $media = Media::query()->firstOrFail();
        expect($media)
            ->church_id->toBe($church->id)
            ->disk->toBe('recordings')
            ->gallery->toBeTrue()
            ->and($media->categories()->where('slug', 'transmissions')->exists())->toBeTrue()
            ->and($recording->refresh()->media_id)->toBe($media->id);

        $this->get(route('gallery.download', $media))
            ->assertSuccessful()
            ->assertDownload(basename($media->file_path));
    } finally {
        if (file_exists($segmentPath)) {
            unlink($segmentPath);
        }

        if (is_dir($recordingsRoot)) {
            rmdir($recordingsRoot);
        }
    }
});
