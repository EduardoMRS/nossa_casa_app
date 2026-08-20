<?php

namespace App\Actions\Media;

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\RecordingStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Recording;
use Illuminate\Support\Facades\DB;

class FinalizeRecording
{
    public function handle(Recording $recording, string $disk, string $destination): void
    {
        DB::transaction(function () use ($recording, $disk, $destination): void {
            $liveStream = $recording->liveStream()->firstOrFail();
            $media = $recording->media;

            if (! $media && $liveStream->church_id && $liveStream->created_by_id) {
                $media = Media::query()->create([
                    'uploader_id' => $liveStream->created_by_id,
                    'church_id' => $liveStream->church_id,
                    'title' => $liveStream->name,
                    'description' => __('media.recording_description', ['date' => now()->toDateTimeString()]),
                    'file_path' => $destination,
                    'disk' => $disk,
                    'mimetype' => $recording->mime_type ?: 'video/mp4',
                    'size' => $recording->size ?? 0,
                    'gallery' => $liveStream->is_public,
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
    }
}
