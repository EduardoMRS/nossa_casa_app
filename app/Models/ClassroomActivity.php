<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassroomActivity extends Model
{
    use HasUlids;

    protected $fillable = [
        'classroom_id', 'form_id', 'created_by_id', 'title', 'instructions',
        'published_at', 'available_until', 'max_attempts', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'available_until' => 'datetime',
            'is_published' => 'boolean',
            'max_attempts' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ClassroomActivitySubmission::class);
    }
}
