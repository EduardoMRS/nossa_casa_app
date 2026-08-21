<?php

namespace App\Events;

use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class LiveStreamUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $connection = 'database';

    public string $liveStreamId;

    public bool $isPublic;

    public string $status;

    public string $embedUrl;

    public ?string $startedAt;

    public ?string $endedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(LiveStream $liveStream)
    {
        $this->liveStreamId = (string) $liveStream->getKey();
        $this->isPublic = $liveStream->is_public;
        $this->status = $liveStream->status->value;
        $this->embedUrl = $liveStream->embed_url;
        $this->startedAt = $liveStream->started_at?->toIso8601String();
        $this->endedAt = $liveStream->ended_at?->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            $this->isPublic
                ? new Channel('live-stream.'.$this->liveStreamId)
                : new PrivateChannel('live-stream.'.$this->liveStreamId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'live-stream.updated';
    }

    /** @return array{id: string, status: string, embed_url: string, started_at: ?string, ended_at: ?string} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->liveStreamId,
            'status' => $this->status,
            'embed_url' => $this->embedUrl,
            'started_at' => $this->startedAt,
            'ended_at' => $this->endedAt,
        ];
    }
}
