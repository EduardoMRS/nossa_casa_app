<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a new or removed chat message to the conversation's private
 * channel so every participant updates in real time (Reverb).
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public ChatMessage $message,
        public bool $deleted = false,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing('user:id,first_name,last_name');

        return [
            'deleted' => $this->deleted,
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'type' => $message->type->value,
                'body' => $message->body,
                'attachment_url' => $message->attachment_url,
                'attachment_mime' => $message->attachment_mime,
                'link_preview' => $message->link_preview,
                'created_at' => $message->created_at?->toIso8601String(),
                'deleted_at' => $message->deleted_at?->toIso8601String(),
                'user' => $message->user?->only(['id', 'first_name', 'last_name']),
            ],
        ];
    }
}
