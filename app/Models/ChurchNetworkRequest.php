<?php

namespace App\Models;

use App\Enums\ChurchNetworkRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchNetworkRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'requesting_church_id',
        'parent_church_id',
        'child_church_id',
        'requested_by_id',
        'responded_by_id',
        'status',
        'responded_at',
    ];

    protected $attributes = [
        'status' => ChurchNetworkRequestStatus::PENDING->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ChurchNetworkRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Church, $this> */
    public function requestingChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'requesting_church_id');
    }

    /** @return BelongsTo<Church, $this> */
    public function parentChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'parent_church_id');
    }

    /** @return BelongsTo<Church, $this> */
    public function childChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'child_church_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }
}
