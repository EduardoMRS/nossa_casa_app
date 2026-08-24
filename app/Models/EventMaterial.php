<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMaterial extends Model
{
    use HasUlids;

    protected $fillable = [
        'event_id',
        'added_by_id',
        'type',
        'title',
        'url',
        'file_path',
        'disk',
        'mimetype',
        'size',
    ];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->type === 'link') {
            return $this->url;
        }

        return $this->file_path ? genUrl($this->file_path, $this->disk) : null;
    }
}
