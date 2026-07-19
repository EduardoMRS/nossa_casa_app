<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'reactionable_type',
        'reactionable_id',
        'content',
        'type',
    ];

    protected $table = 'reactions';
}
