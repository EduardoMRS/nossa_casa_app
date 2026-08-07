<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    use HasTranslations, HasUlids;

    protected $fillable = [
        'calendarable_type',
        'calendarable_id',
        'church_id',
        'date',
    ];

    protected $appends = [
        'translations',
    ];
}
