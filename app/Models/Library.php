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
        'translations',
    ];

    public function categories()
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }
}
