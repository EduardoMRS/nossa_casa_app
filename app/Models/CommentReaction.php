<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CommentReaction extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'commentable_id',
        'commentable_type',
        'icon'
    ];

    protected $table = 'comment_reactions';

    public function media()
    {
        return $this->morphTo(Media::class, 'reactionable');
    }

    public function post()
    {
        return $this->morphTo(Post::class, 'reactionable');
    }

    public function event()
    {
        return $this->morphTo(Event::class, 'reactionable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comment()
    {
        return $this->morphTo(Comment::class, 'commentable');
    }
}
