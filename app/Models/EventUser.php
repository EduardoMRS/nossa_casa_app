<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use LogicException;

class EventUser extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $table = 'event_users';

    protected $fillable = [
        'event_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'status',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirm(User $user): void
    {
        if ($this->user_id === $user->id) {
            $this->status = 'confirmed';
            $this->save();
        } else {
            throw new LogicException(__('common.event_registration_user_mismatch'));
        }
    }

    public function checkIn(User $user): void
    {
        if ($this->user_id === $user->id) {
            $this->check_in_at = now();
            $this->save();
        } else {
            throw new LogicException(__('common.event_registration_user_mismatch'));
        }
    }

    public function checkOut(User $user): void
    {
        if ($this->user_id === $user->id) {
            $this->check_out_at = now();
            $this->save();
        } else {
            throw new LogicException(__('common.event_registration_user_mismatch'));
        }
    }
}
