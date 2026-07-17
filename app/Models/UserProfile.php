<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'location_lang',
        'community_id',
        'avatar_path',
        'gender',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
