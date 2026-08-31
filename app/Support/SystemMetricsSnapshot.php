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
use Illuminate\Support\Str;

class SystemMetricsSnapshot
{
    private const LOG_TAIL_BYTES = 32768;

    private const MAX_LOG_LINES = 15;

    private const MAX_LOG_LINE_LENGTH = 320;

    /** @return array{stats: list<array{label: string, value: int, tone: string}>, queue: array{pending: int, failed: int}, liveStreams: list<array<string, mixed>>, logs: list<string>} */
    public function make(): array
    {
        $logLines = $this->recentApplicationLogLines();

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

    /** @return list<string> */
    public function recentApplicationLogLines(): array
    {
        $configuredPaths = collect(config('logging.channels', []))
            ->pluck('path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->all();
        $discoveredPaths = File::glob(storage_path('logs/*.log')) ?: [];

        return $this->recentLogLinesFromPaths([
            ...$configuredPaths,
            ...$discoveredPaths,
        ]);
    }

    /**
     * @param  list<string>  $logPaths
     * @return list<string>
     */
    public function recentLogLinesFromPaths(array $logPaths): array
    {
        return collect($logPaths)
            ->filter(fn (string $path): bool => File::exists($path) && is_readable($path))
            ->unique()
            ->sortBy(fn (string $path): int => File::lastModified($path))
            ->flatMap(fn (string $path): array => $this->recentLogLines($path))
            ->take(-self::MAX_LOG_LINES)
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function recentLogLines(string $logPath): array
    {
        if (! File::exists($logPath)) {
            return [];
        }

        $handle = fopen($logPath, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);

            if ($fileSize === false || $fileSize === 0) {
                return [];
            }

            $readLength = min($fileSize, self::LOG_TAIL_BYTES);
            fseek($handle, -$readLength, SEEK_END);
            $contents = fread($handle, $readLength);
        } finally {
            fclose($handle);
        }

        if ($contents === false || $contents === '') {
            return [];
        }

        if ($fileSize > $readLength) {
            $contents = Str::after($contents, "\n");
        }

        return collect(preg_split('/\R/', $contents) ?: [])
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->take(-self::MAX_LOG_LINES)
            ->map(fn (string $line): string => Str::limit($line, self::MAX_LOG_LINE_LENGTH))
            ->values()
            ->all();
    }
}
