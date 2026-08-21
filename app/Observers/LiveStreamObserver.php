<?php

namespace App\Observers;

use App\Events\LiveStreamUpdated;
use App\Models\LiveStream;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LiveStreamObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the LiveStream "created" event.
     */
    public function created(LiveStream $liveStream): void
    {
        LiveStreamUpdated::dispatch($liveStream);
    }

    /**
     * Handle the LiveStream "updated" event.
     */
    public function updated(LiveStream $liveStream): void
    {
        LiveStreamUpdated::dispatch($liveStream);
    }

    /**
     * Handle the LiveStream "deleted" event.
     */
    public function deleted(LiveStream $liveStream): void
    {
        LiveStreamUpdated::dispatch($liveStream);
    }
}
