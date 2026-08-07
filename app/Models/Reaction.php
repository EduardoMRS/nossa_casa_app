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

    protected $hidden = [
        'reactionable_type',
        'reactionable_id',
        'updated_at',
        'user'
    ];

    protected $appends = [
        'user_details',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function getUserDetailsAttribute()
    {
        return $this->user?->details ?? null;
    }
}
