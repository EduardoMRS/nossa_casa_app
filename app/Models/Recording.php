<?php

namespace App\Models;

use App\Enums\RecordingStatus;
use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property RecordingStatus $status
 */
class Recording extends Model
{
    /** @use HasFactory<RecordingFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'live_stream_id',
        'worker_id',
        'worker_path',
        'worker_path_hash',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'duration',
        'status',
        'uploaded_at',
        'last_error',
        'media_id',
    ];

    protected $hidden = [
        'worker_path',
        'worker_path_hash',
        'last_error',
    ];

    protected $appends = [
        'url',
    ];

    protected $attributes = [
        'status' => RecordingStatus::WAITING_UPLOAD->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordingStatus::class,
            'uploaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LiveStream, $this> */
    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->status === RecordingStatus::READY && $this->path
            ? genUrl($this->path, $this->disk)
            : null;
    }
}
