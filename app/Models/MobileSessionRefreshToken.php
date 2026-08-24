<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSessionRefreshToken extends Model
{
    use HasUlids;

    protected $fillable = [
        'mobile_session_id',
        'token_hash',
        'used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MobileSession, $this> */
    public function mobileSession(): BelongsTo
    {
        return $this->belongsTo(MobileSession::class);
    }
}
