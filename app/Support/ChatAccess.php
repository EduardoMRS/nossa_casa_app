<?php

namespace App\Support;

use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization for the chat feature.
 *
 * - DIRECT threads: between a member and the church leadership/finance; both
 *   parties plus elevated roles of the church can take part.
 * - CONTEXT threads (Event/Group/Classroom): participation follows the context
 *   membership; the context responsibles plus roles above admin/media moderate.
 * - Chat can be enabled/disabled per context; when disabled nobody can post.
 */
class ChatAccess
{
    /** Church-wide roles that may always moderate chat (above admin/media). */
    public const MODERATOR_ROLES = [
        UserRole::CHURCH_LEADER,
        UserRole::SUPERADMIN,
        UserRole::SYSTEM,
    ];

    /** Roles a member may open a DIRECT thread with. */
    public const STAFF_ROLES = [
        UserRole::LEADER,
        UserRole::TREASURER,
        UserRole::MEDIA,
        UserRole::CHURCH_LEADER,
    ];

    public function __construct(private ChurchAccess $churchAccess) {}

    /**
     * Whether chat is currently available on the context (its on/off switch).
     */
    public function contextChatEnabled(Model $context): bool
    {
        return (bool) ($context->chat_enabled ?? false);
    }

    /**
     * Whether the user can read a conversation.
     */
    public function canView(?User $user, Conversation $conversation): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isChurchModerator($user, $conversation->church_id)) {
            return true;
        }

        if ($conversation->type === ConversationType::DIRECT) {
            return $conversation->participants()->whereKey($user->id)->exists();
        }

        return $this->isContextMember($user, $conversation);
    }

    /**
     * Whether the user can post a message now (view + chat enabled).
     */
    public function canPost(?User $user, Conversation $conversation): bool
    {
        if (! $this->canView($user, $conversation)) {
            return false;
        }

        if ($conversation->type === ConversationType::CONTEXT) {
            $context = $conversation->conversable;

            return $context !== null && $this->contextChatEnabled($context);
        }

        return true;
    }

    /**
     * Whether the user can moderate (delete messages, toggle) a conversation:
     * the context responsible or a church moderator role.
     */
    public function canModerate(?User $user, Conversation $conversation): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isChurchModerator($user, $conversation->church_id)) {
            return true;
        }

        return $conversation->type === ConversationType::CONTEXT
            && $conversation->conversable !== null
            && $this->isContextResponsible($user, $conversation->conversable);
    }

    /**
     * Whether the user can turn chat on/off for a context.
     */
    public function canToggleContext(?User $user, Model $context): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->isChurchModerator($user, $context->church_id)
            || $this->isContextResponsible($user, $context);
    }

    private function isChurchModerator(User $user, ?string $churchId): bool
    {
        if ($user->role === UserRole::SYSTEM) {
            return true;
        }

        return in_array($user->role, self::MODERATOR_ROLES, true)
            && $this->churchAccess->canReachChurch($user, $churchId);
    }

    private function isContextMember(User $user, Conversation $conversation): bool
    {
        $context = $conversation->conversable;

        if ($context === null) {
            return false;
        }

        if ($this->isContextResponsible($user, $context)) {
            return true;
        }

        return match (true) {
            $context instanceof Classroom => $context->members()->whereKey($user->id)->exists(),
            $context instanceof Group => $context->responsibleUsers()->whereKey($user->id)->exists(),
            $context instanceof Event => $context->registrations()
                ->where('user_id', $user->id)
                ->where('status', 'confirmed')
                ->exists(),
            default => false,
        };
    }

    private function isContextResponsible(User $user, Model $context): bool
    {
        return match (true) {
            $context instanceof Classroom => $context->teacher_id === $user->id
                || $this->churchAccess->canManageChurch($user, $context->church_id, [UserRole::LEADER, UserRole::CHURCH_LEADER]),
            $context instanceof Group => $context->responsibleUsers()->whereKey($user->id)->exists()
                || $this->churchAccess->canManageChurch($user, $context->church_id, [UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER]),
            $context instanceof Event => $context->author_id === $user->id
                || $context->responsibleUsers()->whereKey($user->id)->exists()
                || $this->churchAccess->canManageChurch($user, $context->church_id, [UserRole::LEADER, UserRole::CHURCH_LEADER]),
            default => false,
        };
    }
}
