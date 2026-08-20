<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function comment(User $user, Post $post): bool
    {
        if ($user) {
            return true;
        }

        return false;
    }

    public function react(User $user, Post $post): bool
    {
        if ($user) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole(['church_leader', 'superadmin', 'system', 'leader', 'media'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        if ($post->isAuthor($user) || $user->hasRole(['church_leader', 'superadmin', 'system', 'leader', 'media'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        if ($post->isAuthor($user) || $user->hasRole(['church_leader', 'superadmin', 'system', 'leader', 'media'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Post $post): bool
    {
        if ($post->isAuthor($user) || $user->hasRole(['church_leader', 'superadmin', 'system', 'leader', 'media'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        if ($post->isAuthor($user) || $user->hasRole(['church_leader', 'superadmin', 'system'])) {
            return true;
        }

        return false;
    }
}
