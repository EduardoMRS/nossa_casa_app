<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\RecordingSegmentRequest;
use App\Jobs\StoreRecordingInSharedStorage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecordingSegmentController extends Controller
{
    public function __invoke(RecordingSegmentRequest $request): Response
    {
        $segmentPath = $this->validatedSegmentPath($request->validated('segment_path'));
        $contentHash = hash_file('sha256', $segmentPath);

        abort_unless(is_string($contentHash), 422, 'The recording segment could not be hashed.');

        $deliveryId = hash('sha256', $request->validated('path')."\0".$contentHash);

        StoreRecordingInSharedStorage::dispatch(
            path: $request->validated('path'),
            segmentPath: $segmentPath,
            deliveryId: $deliveryId,
            contentHash: $contentHash,
            filename: Str::afterLast(str_replace('\\', '/', $segmentPath), '/'),
            mimeType: File::mimeType($segmentPath) ?: 'application/octet-stream',
            size: File::size($segmentPath),
            duration: $request->validated('segment_duration'),
            workerId: $request->validated('worker_id') ?? (string) config('media.worker_id'),
        )->onQueue('media-recordings');

        return response()->noContent(202);
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
            'The recording segment is outside the configured media node storage.',
        );

        return $resolvedSegmentPath;
    }
}
