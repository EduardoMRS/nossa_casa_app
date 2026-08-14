<?php

namespace App\Http\Controllers\Internal;

use App\Enums\LiveStreamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaStatusHookRequest;
use App\Http\Requests\Media\RecordingCompletedRequest;
use App\Jobs\UploadRecording;
use App\Models\LiveStream;
use App\Models\Recording;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MediaServerWebhookController extends Controller
{
    public function online(MediaStatusHookRequest $request): Response
    {
        $liveStream = LiveStream::query()->where('path', $request->validated('path'))->firstOrFail();

        $liveStream->update([
            'status' => LiveStreamStatus::LIVE,
            'worker_id' => $request->validated('worker_id') ?? config('media.worker_id'),
            'source_type' => $request->validated('source_type'),
            'source_id' => $request->validated('source_id'),
            'started_at' => $liveStream->started_at ?? now(),
            'ended_at' => null,
            'last_error' => null,
            'active_slot' => 1,
        ]);

        return response()->noContent();
    }

    public function offline(MediaStatusHookRequest $request): Response
    {
        $liveStream = LiveStream::query()->where('path', $request->validated('path'))->firstOrFail();

        if ($liveStream->status !== LiveStreamStatus::STOPPED) {
            $liveStream->update([
                'status' => LiveStreamStatus::OFFLINE,
                'ended_at' => now(),
                'source_id' => null,
            ]);
        }

        return response()->noContent();
    }

    public function recordingCompleted(RecordingCompletedRequest $request): Response
    {
        $segmentPath = $this->validatedSegmentPath($request->validated('segment_path'));
        $liveStream = LiveStream::query()->where('path', $request->validated('path'))->firstOrFail();

        $recording = Recording::query()->firstOrCreate(
            ['worker_path_hash' => hash('sha256', $segmentPath)],
            [
                'live_stream_id' => $liveStream->id,
                'worker_id' => $request->validated('worker_id') ?? config('media.worker_id'),
                'worker_path' => $segmentPath,
                'filename' => Str::afterLast(str_replace('\\', '/', $segmentPath), '/'),
                'mime_type' => File::mimeType($segmentPath) ?: 'application/octet-stream',
                'size' => File::size($segmentPath),
                'duration' => $request->validated('segment_duration'),
            ],
        );

        UploadRecording::dispatch($recording->id)->onQueue('media');

        return response()->noContent();
    }

    private function validatedSegmentPath(string $segmentPath): string
    {
        $recordingsRoot = realpath((string) config('media.recordings_root'));
        $resolvedSegmentPath = realpath($segmentPath);

        abort_unless(
            is_string($recordingsRoot)
                && is_string($resolvedSegmentPath)
                && Str::startsWith($resolvedSegmentPath, $recordingsRoot.DIRECTORY_SEPARATOR)
                && File::isFile($resolvedSegmentPath),
            422,
            'The recording segment is outside the configured worker storage.',
        );

        return $resolvedSegmentPath;
    }
}
