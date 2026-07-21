<?php

namespace App\Models;
use App\Models\Post;
use App\Models\Event;
use App\Models\Media;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Highlight extends Model
{
    use HasUlids;
    
    protected $fillable = [
        'highlightable_id',
        'highlightable_type',
        'church_id',
        'order',
    ];

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'highlightable');
    }

    public function events()
    {
        return $this->morphedByMany(Event::class, 'highlightable');
    }

    public function medias()
    {
        return $this->morphedByMany(Media::class, 'highlightable');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslationsAttribute()
    {
        return $this->translations()->pluck('content', 'translatable_column');
    }
}
