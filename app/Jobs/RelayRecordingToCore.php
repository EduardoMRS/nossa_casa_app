<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RelayRecordingToCore implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 12;

    public int $timeout = 3600;

    public function __construct(
        public string $path,
        public string $segmentPath,
        public string $deliveryId,
        public string $filename,
        public ?string $duration,
        public string $workerId,
    ) {}

    public function handle(): void
    {
        if (! File::isFile($this->segmentPath)) {
            throw new RuntimeException('Recording segment is not available on the media node.');
        }

        $stream = fopen($this->segmentPath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Recording segment could not be opened.');
        }

        try {
            Http::baseUrl(rtrim((string) config('media.core_url'), '/'))
                ->withHeaders(['X-Media-Worker-Token' => (string) config('media.worker_token')])
                ->connectTimeout((float) config('media.core_connect_timeout'))
                ->timeout((float) config('media.core_upload_timeout'))
                ->withOptions(['verify' => (bool) config('media.core_verify_tls')])
                ->attach('file', $stream, $this->filename)
                ->post('/api/internal/media/recording-ingest', [
                    'path' => $this->path,
                    'delivery_id' => $this->deliveryId,
                    'filename' => $this->filename,
                    'duration' => $this->duration,
                    'worker_id' => $this->workerId,
                ])
                ->throw();
        } finally {
            fclose($stream);
        }

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
