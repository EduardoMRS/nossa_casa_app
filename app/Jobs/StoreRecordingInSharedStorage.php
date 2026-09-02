<?php

namespace App\Jobs;

use App\Support\RecordingStoragePath;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StoreRecordingInSharedStorage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 12;

    public int $timeout = 3600;

    public function __construct(
        public string $path,
        public string $segmentPath,
        public string $deliveryId,
        public string $contentHash,
        public string $filename,
        public string $mimeType,
        public int $size,
        public ?string $duration,
        public string $workerId,
    ) {}

    public function handle(): void
    {
        if (! File::isFile($this->segmentPath)) {
            throw new RuntimeException(__('media.recording_segment_unavailable'));
        }

        $disk = (string) config('media.archive_disk');
        $destination = RecordingStoragePath::forDelivery($this->deliveryId, $this->filename);
        $storage = Storage::disk($disk);

        if (! $storage->exists($destination) || $storage->size($destination) !== $this->size) {
            $stream = fopen($this->segmentPath, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException(__('media.recording_segment_open_failed'));
            }

            try {
                $stored = $storage->writeStream($destination, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException(__('media.recording_segment_store_failed'));
            }
        }

        if (! $storage->exists($destination) || $storage->size($destination) !== $this->size) {
            throw new RuntimeException(__('media.recording_segment_verify_failed'));
        }

        Http::baseUrl(rtrim((string) config('media.core_url'), '/'))
            ->withHeaders(['X-Media-Worker-Token' => (string) config('media.worker_token')])
            ->connectTimeout((float) config('media.core_connect_timeout'))
            ->timeout((float) config('media.core_request_timeout'))
            ->withOptions(['verify' => (bool) config('media.core_verify_tls')])
            ->post('/api/internal/media/recording-stored', [
                'path' => $this->path,
                'delivery_id' => $this->deliveryId,
                'content_hash' => $this->contentHash,
                'filename' => $this->filename,
                'mime_type' => $this->mimeType,
                'size' => $this->size,
                'duration' => $this->duration,
                'worker_id' => $this->workerId,
            ])
            ->throw();

        File::delete($this->segmentPath);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800, 3600];
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }
}
