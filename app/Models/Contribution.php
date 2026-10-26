<?php

namespace App\Models;

use App\Enums\ContributionType;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A declared tithe or offering. May be anonymous (no user_id).
 */
class Contribution extends Model
{
    /** @use HasFactory<ContributionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'church_id',
        'user_id',
        'type',
        'amount',
        'contributor_name',
        'message',
        'contributed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContributionType::class,
            'amount' => 'decimal:2',
            'contributed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsAnonymousAttribute(): bool
    {
        return $this->user_id === null;
    }
}
