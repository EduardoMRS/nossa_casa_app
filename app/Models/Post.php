<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasUlids;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'published_at',
        'expires_at',
        'author_id',
        'church_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'author_details',
        'category',
        'metrics',
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

    public function getCategoryAttribute()
    {
        return join(', ', $this->categories()->pluck('name')->toArray());
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

    public function scopeVisible($query)
    {
        return $query->where('published_at', '<=', now())
                     ->where(function ($query) {
                         $query->whereNull('expires_at')
                               ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                     ->where('expires_at', '<=', now());
    }

    public function getAuthorDetailsAttribute()
    {
        return $this->author ? $this->author->only(
            [
                'id',
                'first_name',
                'last_name',
                'email'
            ]) : null;
    }

    public function getMetricsAttribute()
    {
        return [
            'comments_count' => $this->comments()->count(),
            'reactions_count' => $this->reactions()->count(),
        ];
    }

    public function classrooms()
    {
        return $this->morphedByMany(Classroom::class, 'postable');
    }
}
