<?php

namespace App\Actions\Media;

use App\Enums\LiveStreamStatus;
use App\Models\LiveStream;
use App\Services\Media\MediaMtxClient;

class StopLiveStream
{
    public function __construct(private MediaMtxClient $mediaMtx) {}

    public function handle(LiveStream $liveStream): void
    {
        $this->mediaMtx->removePath($liveStream);

        $liveStream->update([
            'status' => LiveStreamStatus::STOPPED,
            'ended_at' => $liveStream->ended_at ?? now(),
            'source_id' => null,
            'active_slot' => null,
        ]);
    }
}
