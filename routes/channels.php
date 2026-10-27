<?php

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\LiveStream;
use App\Models\User;
use App\Support\ChatAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('system.metrics', function (User $user): bool {
    return in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);
});

Broadcast::channel('live-stream.{liveStreamId}', function (User $user, string $liveStreamId): bool {
    $liveStream = LiveStream::query()->find($liveStreamId);

    return $liveStream !== null && (
        in_array($user->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)
        || $user->churches()->where('churches.id', $liveStream->church_id)->exists()
    );
}, ['guards' => ['sanctum']]);

Broadcast::channel('chat.conversation.{conversationId}', function (User $user, string $conversationId): bool {
    $conversation = Conversation::query()->find($conversationId);

    return $conversation !== null && app(ChatAccess::class)->canView($user, $conversation);
}, ['guards' => ['sanctum']]);
