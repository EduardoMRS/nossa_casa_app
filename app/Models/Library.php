<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Library extends Model
{
    use HasTranslations, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'type',
        'file_path',
        'church_id',
    ];

    protected $appends = [
        'file_url',
        'translations',
    ];

    public function getFileUrlAttribute(): ?string
    {
        return genUrl($this->file_path);
    }

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
