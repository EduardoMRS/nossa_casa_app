<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'medias';

    protected $fillable = [
        'uploader_id',
        'church_id',
        'title',
        'description',
        'file_path',
        'mimetype',
        'size',
        'gallery',
        'status',
    ];

    protected $casts = [
        'gallery' => 'boolean',
        'status' => MediaStatus::class,
    ];

    protected $appends = [
        'url',
        'uploader_details',
        'translations',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getUploaderDetailsAttribute()
    {
        return $this->uploader?->details ?? null;
    }

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'mediable');
    }

    // Uma mídia pode ter vários comentários
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    public function events()
    {
        return $this->morphedByMany(Event::class, 'mediable');
    }

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('status', MediaStatus::APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', MediaStatus::PENDING);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', MediaStatus::REJECTED);
    }

    public function highlight()
    {
        return $this->morphOne(Highlight::class, 'highlightable');
    }

    public function getUrlAttribute()
    {
        return genUrl($this->file_path);
    }
}
