<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Library extends Model
{
    use HasUlids;
    
    protected $fillable = [
        'title',
        'description',
        'type',
        'file_path',
        'church_id',
    ];

    protected $appends = [
        'translations',
    ];

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
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
