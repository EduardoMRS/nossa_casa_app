<?php

namespace App\Console\Commands;

use App\Enums\RecordingStatus;
use App\Jobs\UploadRecording;
use App\Models\Recording;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:recordings:dispatch')]
#[Description('Dispatch recording segments that are waiting on a media worker')]
class DispatchPendingRecordings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        Recording::query()
            ->whereIn('status', [RecordingStatus::WAITING_UPLOAD, RecordingStatus::FAILED])
            ->whereNotNull('worker_path')
            ->orderBy('created_at')
            ->chunkById(100, function ($recordings) use (&$dispatched): void {
                foreach ($recordings as $recording) {
                    UploadRecording::dispatch($recording->id)->onQueue('media');
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} recording(s).");

        return self::SUCCESS;
    }
}
