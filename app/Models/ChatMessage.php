<?php

namespace App\Models;

use App\Enums\ChatMessageType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single chat message. Carries text and/or one media attachment
 * (image/video/audio) or a link with an OpenGraph preview. Soft deleted when a
 * moderator removes it.
 */
class ChatMessage extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'type',
        'body',
        'attachment_path',
        'attachment_mime',
        'attachment_size',
        'link_preview',
        'deleted_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatMessageType::class,
            'link_preview' => 'array',
            'attachment_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Signed/temporary URL for the media attachment, if any.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? genUrl($this->attachment_path) : null;
    }
}
