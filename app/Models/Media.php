<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Media extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'medias';

    protected $fillable = [
        'uploader_id',
        'church_id',
        'title',
        'original_filename',
        'description',
        'file_path',
        'mimetype',
        'size',
        'gallery',
        'status',
        'disk',
        'optimized_path',
    ];

    protected $casts = [
        'gallery' => 'boolean',
        'status' => MediaStatus::class,
    ];

    protected $hidden = [
        'original_filename',
    ];

    protected $appends = [
        'url',
        'preview_url',
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

    /** @return HasOne<Recording, $this> */
    public function recording(): HasOne
    {
        return $this->hasOne(Recording::class);
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
        $church = $this->relationLoaded('church') ? $this->church : $this->church_id;

        return genUrl($this->file_path, $this->disk, $church);
    }

    public function getPreviewUrlAttribute(): ?string
    {
        $church = $this->relationLoaded('church') ? $this->church : $this->church_id;

        return $this->optimized_path
            ? genUrl($this->optimized_path, $this->disk, $church)
            : $this->url;
    }
}
