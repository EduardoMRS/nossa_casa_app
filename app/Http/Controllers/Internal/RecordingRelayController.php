<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\RelayRecordingRequest;
use App\Jobs\RelayRecordingToCore;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecordingRelayController extends Controller
{
    public function __invoke(RelayRecordingRequest $request): Response
    {
        abort_unless(config('media.role') === 'relay', 404);

        $segmentPath = $this->validatedSegmentPath($request->validated('segment_path'));
        $contentHash = hash_file('sha256', $segmentPath);

        abort_unless(is_string($contentHash), 422, 'The recording segment could not be hashed.');

        $deliveryId = hash('sha256', $request->validated('path')."\0".$contentHash);

        RelayRecordingToCore::dispatch(
            path: $request->validated('path'),
            segmentPath: $segmentPath,
            deliveryId: $deliveryId,
            filename: Str::afterLast(str_replace('\\', '/', $segmentPath), '/'),
            duration: $request->validated('segment_duration'),
            workerId: $request->validated('worker_id') ?? (string) config('media.worker_id'),
        )->onQueue('media-relay');

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
