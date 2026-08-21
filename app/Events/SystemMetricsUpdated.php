<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemMetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    /** @param array<string, mixed> $snapshot */
    public function __construct(public array $snapshot) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('system.metrics'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'system.metrics.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
