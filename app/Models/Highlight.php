<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Highlight extends Model
{
    use HasTranslations, HasUlids;

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
}
