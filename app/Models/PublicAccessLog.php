<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anonymous access log for public pages.
 *
 * This model intentionally never stores a user identifier. Visitors are
 * represented only by a hashed IP address and an approximate origin resolved
 * from that IP, so the records power the access flow and visitor map without
 * identifying individuals.
 */
class PublicAccessLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'church_id',
        'ip_hash',
        'country_code',
        'country',
        'region',
        'city',
        'latitude',
        'longitude',
        'path',
        'route_name',
        'referer_host',
        'user_agent',
        'locale',
        'visited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'visited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
