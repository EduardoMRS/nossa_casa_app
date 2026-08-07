<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Vercicle extends Model
{
    use HasTranslations, HasUlids;

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

    public function library()
    {
        return $this->belongsTo(Library::class);
    }
}
