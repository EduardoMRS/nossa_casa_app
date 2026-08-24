<?php

namespace App\Queries;

use App\Data\CanonicalData;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\LiveStream;
use App\Models\User;

final class LiveStreamQuery
{
    public function show(LiveStream $liveStream, string $churchId, ?User $user): CanonicalData
    {
        abort_unless($liveStream->church_id === $churchId, 404);
        $role = $user?->role;
        $hasChurchAccess = in_array($role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)
            || $user?->churches()->whereKey($churchId)->exists();

        abort_unless($liveStream->is_public || $hasChurchAccess, 404);

        $comments = $liveStream->comments()
            ->with('user:id,first_name,last_name')
            ->orderByDesc('is_pinned')
            ->orderByDesc('pinned_at')
            ->latest()
            ->get()
            ->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'content' => $comment->content,
                'is_pinned' => (bool) $comment->is_pinned,
                'created_at' => $comment->created_at?->toISOString(),
                'user' => $comment->user ? [
                    'id' => $comment->user->id,
                    'first_name' => $comment->user->first_name,
                    'last_name' => $comment->user->last_name,
                ] : null,
            ])->all();

        return new CanonicalData([
            'liveStream' => [
                'id' => $liveStream->id,
                'name' => $liveStream->name,
                'status' => $liveStream->status->value,
                'embed_url' => $liveStream->embed_url,
                'started_at' => $liveStream->started_at?->toISOString(),
                'is_public' => (bool) $liveStream->is_public,
            ],
            'realtimePrivate' => ! $liveStream->is_public,
            'comments' => $comments,
            'canComment' => $user !== null,
            'canModerate' => $hasChurchAccess && in_array($role, [
                UserRole::LEADER,
                UserRole::MEDIA,
                UserRole::CHURCH_LEADER,
                UserRole::SUPERADMIN,
                UserRole::SYSTEM,
            ], true),
        ]);
    }
}
