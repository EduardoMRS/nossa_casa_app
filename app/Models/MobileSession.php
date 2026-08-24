<?php

namespace App\Models;

use Database\Factories\MobileSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken;

class MobileSession extends Model
{
    /** @use HasFactory<MobileSessionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'platform',
        'app_version',
        'token_family',
        'current_access_token_id',
        'active_slot',
        'expires_at',
        'last_used_at',
        'last_ip',
        'user_agent',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'active_slot' => 'integer',
            'expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PersonalAccessToken, $this> */
    public function currentAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'current_access_token_id');
    }

    /** @return HasMany<MobileSessionRefreshToken, $this> */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(MobileSessionRefreshToken::class);
    }

    /** @return HasMany<DevicePushToken, $this> */
    public function devicePushTokens(): HasMany
    {
        return $this->hasMany(DevicePushToken::class);
    }
}
