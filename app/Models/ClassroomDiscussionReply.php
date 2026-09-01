<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassroomDiscussionReply extends Model
{
    use HasUlids;

    protected $fillable = [
        'classroom_discussion_id', 'author_id', 'parent_id', 'content',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(ClassroomDiscussion::class, 'classroom_discussion_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
