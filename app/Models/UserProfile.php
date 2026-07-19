<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UserProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'phone',
        'location_lang',
        'community_id',
        'avatar_path',
        'gender',
    ];

    protected $table = 'user_profiles';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
