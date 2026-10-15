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
        'document_type',
        'document_number',
        'location_lang',
        'church_id',
        'community_id',
        'avatar_path',
        'gender',
        'medical_notes',
    ];

    protected $table = 'user_profiles';

    protected $hidden = [
        'document_type',
        'document_number',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (UserProfile $profile): void {
            if (! $profile->church_id || ! $profile->user_id) {
                return;
            }

            $role = $profile->user()->value('role');

            $profile->user?->churches()->syncWithoutDetaching([
                $profile->church_id => ['role' => $role],
            ]);
        });
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return genUrl($this->avatar_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
