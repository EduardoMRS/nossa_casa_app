<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'phone',
        'location_lang',
        'church_id',
        'community_id',
        'avatar_path',
        'gender',
        'medical_notes',
    ];

    protected $table = 'user_profiles';

    protected $appends = [
        'avatar_url',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return genUrl($this->avatar_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
