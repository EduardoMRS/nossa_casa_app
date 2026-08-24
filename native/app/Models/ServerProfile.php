<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServerProfile extends Model
{
    protected $fillable = [
        'instance_id',
        'name',
        'origin',
        'api_base_url',
        'web_base_url',
        'api_version',
        'capabilities',
        'realtime',
        'selected_church_id',
        'selected',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'api_version' => 'integer',
            'capabilities' => 'array',
            'realtime' => 'array',
            'selected' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function caches(): HasMany
    {
        return $this->hasMany(ContentCache::class);
    }
}
