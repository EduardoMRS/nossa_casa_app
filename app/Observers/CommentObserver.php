<?php

namespace App\Observers;

use App\Events\LiveStreamCommentsUpdated;
use App\Models\Comment;
use App\Models\LiveStream;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CommentObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        $this->broadcastLiveStreamComments($comment);
    }

    /**
     * Handle the Comment "updated" event.
     */
    public function updated(Comment $comment): void
    {
        $this->broadcastLiveStreamComments($comment);
    }

    /**
     * Handle the Comment "deleted" event.
     */
    public function deleted(Comment $comment): void
    {
        $this->broadcastLiveStreamComments($comment);
    }

    /**
     * Handle the Comment "restored" event.
     */
    private function broadcastLiveStreamComments(Comment $comment): void
    {
        if ($comment->commentable_type === LiveStream::class) {
            LiveStreamCommentsUpdated::dispatch((string) $comment->commentable_id);
        }
    }
}
