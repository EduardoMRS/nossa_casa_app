<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    use HasUlids;
    
    protected $fillable = [
        'calendarable_type',
        'calendarable_id',
        'church_id',
        'date',
    ];

    protected $appends = [
        'translations',
    ];

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable')->pluck('content', 'translatable_column');
    }
}
