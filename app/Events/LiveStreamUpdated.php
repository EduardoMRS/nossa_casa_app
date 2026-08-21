<?php

namespace App\Events;

use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStreamUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public LiveStream $liveStream) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            $this->liveStream->is_public
                ? new Channel('live-stream.'.$this->liveStream->getKey())
                : new PrivateChannel('live-stream.'.$this->liveStream->getKey()),
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
            'id' => (string) $this->liveStream->getKey(),
            'status' => $this->liveStream->status->value,
            'embed_url' => $this->liveStream->embed_url,
            'started_at' => $this->liveStream->started_at?->toIso8601String(),
            'ended_at' => $this->liveStream->ended_at?->toIso8601String(),
        ];
    }
}
