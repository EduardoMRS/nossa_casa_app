<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;
use App\Support\ChurchAccess;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        if ($post->visibility === 'public') {
            return true;
        }

        if ($this->canManage($user, $post)) {
            return true;
        }

        if ($post->visibility === 'classroom_private') {
            return $post->classrooms()
                ->where(fn ($query) => $query
                    ->where('classrooms.teacher_id', $user->id)
                    ->orWhereHas('members', fn ($members) => $members->whereKey($user->id)))
                ->exists();
        }

        if ($post->visibility === 'event_private') {
            return $post->privateEvents()
                ->whereHas('registrations', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', 'confirmed'))
                ->exists();
        }

        return false;
    }

    public function comment(User $user, Post $post): bool
    {
        return $post->comments_enabled && $this->view($user, $post);
    }

    public function react(User $user, Post $post): bool
    {
        return $post->reactions_enabled && $this->view($user, $post);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER,
            UserRole::SYSTEM,
        ], true);
    }

    public function update(User $user, Post $post): bool
    {
        return $post->isAuthor($user) || $this->canManage($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $post->isAuthor($user)
            || $user->role === UserRole::SYSTEM
            || app(ChurchAccess::class)->canManageChurch($user, $post->church_id, [UserRole::CHURCH_LEADER]);
    }

    private function canManage(User $user, Post $post): bool
    {
        if ($user->role === UserRole::SYSTEM) {
            return true;
        }

        return app(ChurchAccess::class)->canManageChurch($user, $post->church_id, [
            UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER,
        ]);
    }
}
