<?php

namespace App\Jobs;

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\RecordingStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Recording;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UploadRecording implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 600;

    public function __construct(public string $recordingId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recording = Recording::query()->findOrFail($this->recordingId);

        if ($recording->status === RecordingStatus::READY) {
            return;
        }

        if (! File::isFile($recording->worker_path)) {
            throw new RuntimeException('Recording segment is not available on the worker.');
        }

        $disk = (string) config('media.archive_disk');
        $destination = 'live-streams/'.$recording->live_stream_id.'/'.$recording->id.'-'.$recording->filename;
        $stream = fopen($recording->worker_path, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Recording segment could not be opened.');
        }

        $recording->update([
            'status' => RecordingStatus::UPLOADING,
            'last_error' => null,
        ]);

        try {
            $stored = Storage::disk($disk)->writeStream($destination, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException('Recording segment could not be stored.');
        }

        DB::transaction(function () use ($recording, $disk, $destination): void {
            $liveStream = $recording->liveStream()->firstOrFail();
            $media = null;

            if ($liveStream->church_id && $liveStream->created_by_id) {
                $media = Media::query()->create([
                    'uploader_id' => $liveStream->created_by_id,
                    'church_id' => $liveStream->church_id,
                    'title' => $liveStream->name,
                    'description' => __('Recording of the live stream from :date.', ['date' => now()->toDateTimeString()]),
                    'file_path' => $destination,
                    'disk' => $disk,
                    'mimetype' => $recording->mime_type ?: 'video/mp4',
                    'size' => $recording->size ?? 0,
                    'gallery' => true,
                    'status' => MediaStatus::APPROVED,
                ]);

                $category = Category::query()->firstOrCreate([
                    'church_id' => $liveStream->church_id,
                    'slug' => 'transmissions',
                    'type' => CategoryType::MEDIA->value,
                ], [
                    'name' => 'Transmissions',
                ]);

                $media->categories()->syncWithoutDetaching([$category->id]);
            }

            $recording->update([
                'media_id' => $media?->id,
                'disk' => $disk,
                'path' => $destination,
                'status' => RecordingStatus::READY,
                'uploaded_at' => now(),
                'last_error' => null,
            ]);
        });

        File::delete($recording->worker_path);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    public function uniqueId(): string
    {
        return $this->recordingId;
    }

    public function failed(?Throwable $exception): void
    {
        Recording::query()->whereKey($this->recordingId)->update([
            'status' => RecordingStatus::FAILED,
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
