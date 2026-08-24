<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContentCache extends Model
{
    protected $fillable = [
        'server_profile_id',
        'cache_key',
        'payload',
        'expires_at',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(ServerProfile::class, 'server_profile_id');
    }
}
