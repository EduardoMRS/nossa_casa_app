<?php

namespace App\Http\Controllers\Internal;

use App\Enums\LiveStreamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaStatusHookRequest;
use App\Models\LiveStream;
use Illuminate\Http\Response;

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
                'status' => $liveStream->ends_on_disconnect
                    ? LiveStreamStatus::STOPPED
                    : LiveStreamStatus::OFFLINE,
                'ended_at' => now(),
                'source_id' => null,
            ]);
        }

        return response()->noContent();
    }
}
