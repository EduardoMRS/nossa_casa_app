<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomActivitySubmission extends Model
{
    use HasUlids;

    protected $fillable = [
        'classroom_activity_id', 'user_id', 'answers', 'attempt', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'attempt' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClassroomActivity::class, 'classroom_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
