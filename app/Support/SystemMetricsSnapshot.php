<?php

namespace App\Support;

use App\Enums\LiveStreamStatus;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Form;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SystemMetricsSnapshot
{
    /** @return array{stats: list<array{label: string, value: int, tone: string}>, queue: array{pending: int, failed: int}, liveStreams: list<array<string, mixed>>, logs: list<string>} */
    public function make(): array
    {
        $logPath = storage_path('logs/laravel.log');
        $logLines = File::exists($logPath)
            ? array_values(collect(preg_split('/\R/', File::get($logPath)) ?: [])->filter()->take(-100)->values()->all())
            : [];

        return [
            'stats' => [
                ['label' => 'users', 'value' => User::query()->count(), 'tone' => 'indigo'],
                ['label' => 'events', 'value' => Event::query()->count(), 'tone' => 'emerald'],
                ['label' => 'posts', 'value' => Post::query()->count(), 'tone' => 'amber'],
                ['label' => 'comments', 'value' => Comment::query()->count(), 'tone' => 'rose'],
                ['label' => 'media', 'value' => Media::query()->count(), 'tone' => 'sky'],
                ['label' => 'forms', 'value' => Form::query()->count(), 'tone' => 'violet'],
            ],
            'queue' => [
                'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ],
            'liveStreams' => array_values(LiveStream::query()
                ->where('status', LiveStreamStatus::LIVE)
                ->withCount('recordings')
                ->latest('started_at')
                ->get()
                ->map(fn (LiveStream $liveStream): array => [
                    'id' => $liveStream->id,
                    'name' => $liveStream->name,
                    'path' => $liveStream->path,
                    'worker_id' => $liveStream->worker_id,
                    'source_type' => $liveStream->source_type,
                    'started_at' => $liveStream->started_at?->toIso8601String(),
                    'recordings_count' => $liveStream->recordings_count,
                    'playback_url' => $liveStream->playback_url,
                ])
                ->values()
                ->all()),
            'logs' => $logLines,
        ];
    }
}
