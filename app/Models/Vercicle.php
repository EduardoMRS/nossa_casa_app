<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Vercicle extends Model
{
    use HasUlids;

    protected $fillable = [
        'library_id',
        'book',
        'chapter',
        'verse',
        'content',
        'version',
    ];

    protected $appends = [
        'translations',
    ];

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function getTranslationsAttribute()
    {
        return $this->translations()->pluck('content', 'translatable_column');
    }
}
