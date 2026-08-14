<?php

namespace App\Http\Controllers\Internal;

use App\Actions\Media\FinalizeRecording;
use App\Enums\RecordingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\IngestRecordingRequest;
use App\Models\LiveStream;
use App\Models\Recording;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RecordingIngestController extends Controller
{
    public function __invoke(IngestRecordingRequest $request, FinalizeRecording $finalizeRecording): Response
    {
        abort_if(config('media.role') === 'relay', 404);

        $liveStream = LiveStream::query()->where('path', $request->validated('path'))->firstOrFail();
        $upload = $request->file('file');
        $filename = Str::afterLast(str_replace('\\', '/', $request->validated('filename')), '/');
        $contentHash = hash_file('sha256', $upload->getRealPath());

        abort_unless(
            is_string($contentHash)
                && hash_equals(
                    $request->validated('delivery_id'),
                    hash('sha256', $request->validated('path')."\0".$contentHash),
                ),
            422,
            'The uploaded recording checksum is invalid.',
        );

        $recording = Recording::query()->firstOrCreate(
            ['worker_path_hash' => $request->validated('delivery_id')],
            [
                'live_stream_id' => $liveStream->id,
                'worker_id' => $request->validated('worker_id'),
                'worker_path' => 'relay://'.$request->validated('worker_id').'/'.$filename,
                'filename' => $filename,
                'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                'size' => $upload->getSize(),
                'duration' => $request->validated('duration'),
            ],
        );

        if ($recording->status === RecordingStatus::READY) {
            return response()->noContent();
        }

        $disk = (string) config('media.archive_disk');
        $destination = 'live-streams/'.$liveStream->id.'/'.$recording->id.'-'.$filename;
        $stream = fopen($upload->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('The uploaded recording could not be opened.');
        }

        $recording->update([
            'status' => RecordingStatus::UPLOADING,
            'last_error' => null,
        ]);

        try {
            $stored = Storage::disk($disk)->writeStream($destination, $stream);

            if (! $stored) {
                throw new RuntimeException('The uploaded recording could not be stored.');
            }

            $finalizeRecording->handle($recording, $disk, $destination);
        } catch (Throwable $exception) {
            $recording->update([
                'status' => RecordingStatus::FAILED,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            fclose($stream);
        }

        return response()->noContent();
    }
}
