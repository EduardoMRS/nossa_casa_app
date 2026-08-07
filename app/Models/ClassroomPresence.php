<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomPresence extends Model
{
    use HasUlids;

    protected $fillable = [
        'classroom_id',
        'user_id',
        'check_in',
        'check_out',
        'checkout_pin',
        'checkout_pin_code',
        'pin_generated_at',
    ];

    protected $hidden = ['checkout_pin', 'checkout_pin_code'];

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'pin_generated_at' => 'datetime',
            'checkout_pin_code' => 'encrypted',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
