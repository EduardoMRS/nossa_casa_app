<?php

namespace App\Events;

use App\Models\Comment;
use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStreamCommentsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public string $liveStreamId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $liveStream = LiveStream::query()->find($this->liveStreamId);

        return [
            $liveStream?->is_public
                ? new Channel('live-stream.'.$this->liveStreamId)
                : new PrivateChannel('live-stream.'.$this->liveStreamId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'live-stream.comments.updated';
    }

    /** @return array{comments: list<array<string, mixed>>} */
    public function broadcastWith(): array
    {
        $liveStream = LiveStream::query()->find($this->liveStreamId);

        if ($liveStream === null) {
            return ['comments' => []];
        }

        return [
            'comments' => array_values($liveStream->comments()
                ->with('user:id,first_name,last_name')
                ->orderByDesc('is_pinned')
                ->orderByDesc('pinned_at')
                ->latest()
                ->get()
                ->map(fn (Comment $comment): array => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'is_pinned' => $comment->is_pinned,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'user' => $comment->user?->only(['id', 'first_name', 'last_name']),
                ])
                ->values()
                ->all()),
        ];
    }
}
