<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\LiveStream;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicLiveStreamController extends Controller
{
    public function show(Request $request, LiveStream $liveStream): Response
    {
        $churchId = app(ChurchDomainContext::class)->churchId();

        abort_unless($churchId && $liveStream->church_id === $churchId, 404);

        $user = $request->user();
        $role = $user?->role;
        $hasChurchAccess = $role === UserRole::SYSTEM || $user?->profile?->church_id === $churchId;

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
                'is_pinned' => $comment->is_pinned,
                'created_at' => $comment->created_at,
                'user' => $comment->user,
            ]);

        return Inertia::render('LiveStreams/Show', [
            'liveStream' => [
                'id' => $liveStream->id,
                'name' => $liveStream->name,
                'status' => $liveStream->status->value,
                'embed_url' => $liveStream->embed_url,
                'started_at' => $liveStream->started_at,
                'is_public' => $liveStream->is_public,
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
