<?php

use App\Enums\UserRole;
use App\Models\LiveStream;
use App\Models\User;
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
        $user->role === UserRole::SYSTEM
        || $user->profile?->church_id === $liveStream->church_id
    );
});
