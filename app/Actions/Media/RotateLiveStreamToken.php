<?php

namespace App\Actions\Media;

use App\Enums\LiveStreamStatus;
use App\Models\LiveStream;
use App\Services\Media\MediaMtxClient;
use Illuminate\Support\Str;
use Throwable;

class RotateLiveStreamToken
{
    public function __construct(private MediaMtxClient $mediaMtx) {}

    public function handle(LiveStream $liveStream): string
    {
        abort_unless($liveStream->input_mode === 'publisher', 422, 'Only publisher streams have a token.');
        abort_unless($liveStream->active_slot === 1, 422, 'Only an active stream can rotate its token.');

        $token = Str::random(64);
        $this->mediaMtx->removePath($liveStream);

        $liveStream->update([
            'publish_token' => $token,
            'token_rotated_at' => now(),
            'status' => LiveStreamStatus::READY,
            'started_at' => null,
            'ended_at' => now(),
            'source_id' => null,
            'last_error' => null,
        ]);

        try {
            $this->mediaMtx->addPath($liveStream);
        } catch (Throwable $exception) {
            $liveStream->update([
                'status' => LiveStreamStatus::FAILED,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $token;
    }
}
