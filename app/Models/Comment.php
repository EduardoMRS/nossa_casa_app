<?php

namespace App\Models;

use App\Observers\CommentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read User|null $user
 */
#[ObservedBy(CommentObserver::class)]
class Comment extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
        'content',
        'is_pinned',
        'pinned_by_id',
        'pinned_at',
    ];

    protected $table = 'comments';

    protected $appends = [
        'user_details',
    ];

    protected $hidden = [
        'commentable_type',
        'commentable_id',
        'updated_at',
        'user',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
        ];
    }

    public function post()
    {
        return $this->morphTo(Post::class, 'commentable');
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function media()
    {
        return $this->morphTo(Media::class, 'commentable');
    }

    public function event()
    {
        return $this->morphTo(Event::class, 'commentable');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUserDetailsAttribute()
    {
        return $this->user?->details ?? null;
    }

    // Auto-relacionamento polimórfico: Um comentário pode ter respostas (outros comentários)
    public function replies()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    // Reações aos comentários
    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    public function getMetricsAttribute()
    {
        return [
            'replies_count' => $this->replies()->count(),
            'reactions_count' => $this->reactions()->count(),
        ];
    }
}
