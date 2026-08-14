<?php

namespace App\Models;

use App\Enums\LiveStreamStatus;
use Database\Factories\LiveStreamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property LiveStreamStatus $status
 */
class LiveStream extends Model
{
    /** @use HasFactory<LiveStreamFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'church_id',
        'created_by_id',
        'name',
        'path',
        'source_url',
        'input_mode',
        'publish_token',
        'token_rotated_at',
        'source_on_demand',
        'record',
        'is_public',
        'status',
        'worker_id',
        'source_type',
        'source_id',
        'started_at',
        'ended_at',
        'last_error',
        'active_slot',
    ];

    protected $hidden = [
        'source_url',
        'source_id',
        'last_error',
        'publish_token',
    ];

    protected $appends = [
        'playback_url',
        'embed_url',
    ];

    protected $attributes = [
        'source_on_demand' => false,
        'record' => true,
        'is_public' => true,
        'status' => LiveStreamStatus::READY->value,
    ];

    protected static function booted(): void
    {
        static::saving(function (LiveStream $liveStream): void {
            $status = $liveStream->status instanceof LiveStreamStatus
                ? $liveStream->status
                : LiveStreamStatus::from((string) $liveStream->status);

            $liveStream->active_slot = $status->reservesChurchSlot() ? 1 : null;
        });
    }

    protected function casts(): array
    {
        return [
            'source_url' => 'encrypted',
            'source_on_demand' => 'boolean',
            'record' => 'boolean',
            'is_public' => 'boolean',
            'status' => LiveStreamStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_slot' => 'integer',
            'publish_token' => 'encrypted',
            'token_rotated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Church, $this> */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<Recording, $this> */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    /**
     * @param  Builder<LiveStream>  $query
     * @return Builder<LiveStream>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function getPlaybackUrlAttribute(): string
    {
        return rtrim((string) config('media.mediamtx.public_hls_url'), '/')
            .'/'.$this->path.'/index.m3u8';
    }

    public function getEmbedUrlAttribute(): string
    {
        return rtrim((string) config('media.mediamtx.public_hls_url'), '/')
            .'/'.$this->path.'?controls=true&muted=false&autoplay=true&playsInline=true';
    }
}
