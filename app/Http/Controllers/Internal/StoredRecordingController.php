<?php

namespace App\Http\Controllers\Internal;

use App\Actions\Media\FinalizeRecording;
use App\Enums\RecordingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\RegisterStoredRecordingRequest;
use App\Models\LiveStream;
use App\Models\Recording;
use App\Support\RecordingStoragePath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoredRecordingController extends Controller
{
    public function __invoke(RegisterStoredRecordingRequest $request, FinalizeRecording $finalizeRecording): Response
    {
        abort_unless(config('media.role') === 'core', 404);

        $path = $request->validated('path');
        $contentHash = $request->validated('content_hash');

        abort_unless(
            hash_equals(
                $request->validated('delivery_id'),
                hash('sha256', $path."\0".$contentHash),
            ),
            422,
            __('media.stored_recording_checksum_invalid'),
        );

        $liveStream = LiveStream::query()->where('path', $path)->firstOrFail();
        $filename = Str::afterLast(str_replace('\\', '/', $request->validated('filename')), '/');
        $destination = RecordingStoragePath::forDelivery($request->validated('delivery_id'), $filename);
        $disk = (string) config('media.archive_disk');

        abort_unless(
            Storage::disk($disk)->exists($destination)
                && Storage::disk($disk)->size($destination) === $request->integer('size'),
            422,
            __('media.shared_recording_unavailable'),
        );

        $recording = Recording::query()->firstOrCreate(
            ['worker_path_hash' => $request->validated('delivery_id')],
            [
                'live_stream_id' => $liveStream->id,
                'worker_id' => $request->validated('worker_id'),
                'worker_path' => 'storage://'.$disk.'/'.$destination,
                'filename' => $filename,
                'mime_type' => $request->validated('mime_type'),
                'size' => $request->integer('size'),
                'duration' => $request->validated('duration'),
            ],
        );

        if ($recording->status === RecordingStatus::READY) {
            return response()->noContent();
        }

        try {
            $recording->update([
                'status' => RecordingStatus::UPLOADING,
                'last_error' => null,
            ]);

            $finalizeRecording->handle($recording, $disk, $destination);
        } catch (Throwable $exception) {
            $recording->update([
                'status' => RecordingStatus::FAILED,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return response()->noContent();
    }
}
