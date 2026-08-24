<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\PushTransport;
use Database\Factories\DevicePushTokenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePushToken extends Model
{
    /** @use HasFactory<DevicePushTokenFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'app_instance_id',
        'user_id',
        'mobile_session_id',
        'device_id',
        'transport',
        'token_hash',
        'token',
        'app_version',
        'locale',
        'enabled_categories',
        'last_seen_at',
        'invalidated_at',
    ];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'transport' => PushTransport::class,
            'token' => 'encrypted',
            'enabled_categories' => 'array',
            'last_seen_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }

    public function accepts(NotificationCategory $category): bool
    {
        return in_array($category->value, $this->enabled_categories, true);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AppInstance, $this> */
    public function appInstance(): BelongsTo
    {
        return $this->belongsTo(AppInstance::class);
    }

    /** @return BelongsTo<MobileSession, $this> */
    public function mobileSession(): BelongsTo
    {
        return $this->belongsTo(MobileSession::class);
    }
}
