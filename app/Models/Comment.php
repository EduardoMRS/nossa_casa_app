<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
        'content'
    ];

    protected $table = 'comments';

    public function post()
    {
        return $this->morphTo(Post::class, 'commentable');
    }

    public function media()
    {
        return $this->morphTo(Media::class, 'commentable');
    }

    public function event()
    {
        return $this->morphTo(Event::class, 'commentable');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
}
