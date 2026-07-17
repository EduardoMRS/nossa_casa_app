<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasUlids;

    protected $fillable = [
        'author_id',
        'church_id',
        'title',
        'slug',
        'content',
        'published_at',
        'expires_at'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    // Relacionamento Polimórfico: Um post pode ter várias categorias
    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    // Relacionamento Polimórfico: Um post pode ter várias mídias (capa, anexos, galeria interna)
    public function medias()
    {
        return $this->morphToMany(Media::class, 'mediable');
    }

    // Relacionamento Polimórfico: Um post pode ter vários comentários
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }
}
