<?php

namespace App\Http\Controllers;

use App\Enums\ChatMessageType;
use App\Enums\ConversationType;
use App\Enums\UserRole;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\Classroom;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Services\LinkPreviewFetcher;
use App\Support\ChatAccess;
use App\Support\ChurchAccess;
use App\Support\ChurchDomainContext;
use App\Traits\UploadsMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ChatController extends Controller
{
    use UploadsMedia;

    /** @var array<string, class-string<Model>> */
    private const CONTEXT_MODELS = [
        'event' => Event::class,
        'group' => Group::class,
        'classroom' => Classroom::class,
    ];

    public function __construct(
        private readonly ChatAccess $chat,
        private readonly ChurchAccess $churchAccess,
        private readonly ChurchDomainContext $context,
    ) {}

    /**
     * Conversations the current user can see.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where('church_id', $this->churchId($request))
            ->with(['conversable', 'participants:id,first_name,last_name'])
            ->orderByDesc('last_message_at')
            ->get()
            ->filter(fn (Conversation $conversation): bool => $this->chat->canView($user, $conversation))
            ->map(fn (Conversation $conversation): array => $this->conversationData($conversation, $user))
            ->values();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Messages of a conversation (most recent first, paginated).
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($this->chat->canView($request->user(), $conversation), 403);

        $messages = $conversation->messages()
            ->with('user:id,first_name,last_name')
            ->latest()
            ->paginate(30)
            ->through(fn (ChatMessage $message): array => $this->messageData($message));

        $conversation->participants()->updateExistingPivot($request->user()->id, [
            'last_read_at' => Carbon::now(),
        ]);

        return response()->json([
            'conversation' => $this->conversationData($conversation, $request->user()),
            'messages' => $messages,
            'can_moderate' => $this->chat->canModerate($request->user(), $conversation),
            'can_post' => $this->chat->canPost($request->user(), $conversation),
        ]);
    }

    /**
     * Send a message (text, media attachment or link).
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($this->chat->canPost($request->user(), $conversation), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => [
                'nullable',
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,audio/mpeg,audio/mp4,audio/ogg,audio/webm,audio/wav',
                'required_without:body',
            ],
        ]);

        $attributes = [
            'user_id' => $request->user()->id,
            'type' => ChatMessageType::TEXT,
            'body' => $validated['body'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mime = (string) $file->getMimeType();
            $attributes['type'] = $this->attachmentType($mime);
            $attributes['attachment_path'] = $this->handleMediaUpload(
                $file,
                'church/'.$conversation->church_id.'/chat/'.$conversation->id,
            );
            $attributes['attachment_mime'] = $mime;
            $attributes['attachment_size'] = $file->getSize();
        } elseif (filled($validated['body'] ?? null) && ($url = $this->firstUrl($validated['body'])) !== null) {
            $preview = app(LinkPreviewFetcher::class)->fetch($url);

            if ($preview !== null) {
                $attributes['type'] = ChatMessageType::LINK;
                $attributes['link_preview'] = $preview;
            }
        }

        $message = $conversation->messages()->create($attributes);
        $conversation->update(['last_message_at' => $message->created_at]);

        ChatMessageSent::dispatch($conversation->id, $message);

        return response()->json(['message' => $this->messageData($message->fresh('user'))], 201);
    }

    /**
     * Delete (moderate) a message.
     */
    public function destroyMessage(Request $request, Conversation $conversation, ChatMessage $message): JsonResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        $isAuthor = $message->user_id === $request->user()->id;
        abort_unless($isAuthor || $this->chat->canModerate($request->user(), $conversation), 403);

        $message->update(['deleted_by_id' => $request->user()->id]);
        $message->delete();

        ChatMessageSent::dispatch($conversation->id, $message, deleted: true);

        return response()->json(status: 204);
    }

    /**
     * Turn chat on/off for a context (Event/Group/Classroom).
     */
    public function toggleContext(Request $request, string $contextType, string $contextId): JsonResponse
    {
        $context = $this->resolveContext($contextType, $contextId);
        abort_unless($this->chat->canToggleContext($request->user(), $context), 403);

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $context->forceFill(['chat_enabled' => $validated['enabled']])->save();

        if ($validated['enabled']) {
            $this->conversationForContext($context);
        }

        return response()->json(['chat_enabled' => $context->chat_enabled]);
    }

    /**
     * Resolve (creating if needed) the conversation of a context and return it.
     */
    public function showContext(Request $request, string $contextType, string $contextId): JsonResponse
    {
        $context = $this->resolveContext($contextType, $contextId);
        $conversation = $this->conversationForContext($context);
        abort_unless($this->chat->canView($request->user(), $conversation), 403);

        return $this->messages($request, $conversation);
    }

    /**
     * Open (or reuse) a DIRECT thread between the authenticated member and the
     * church contact (its leadership/pastor), so "Talk to the church" works
     * without the member having to pick a recipient.
     */
    public function openChurchContact(Request $request): JsonResponse
    {
        $churchId = $this->churchId($request);
        abort_unless(is_string($churchId) && $churchId !== '', 422, __('church.context.required'));

        $actor = $request->user();
        abort_unless($this->churchAccess->canReachChurch($actor, $churchId), 403);

        $contact = User::query()
            ->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))
            ->whereIn('role', [UserRole::CHURCH_LEADER->value, UserRole::LEADER->value])
            ->where('id', '!=', $actor->id)
            ->orderByRaw('case when role = ? then 0 else 1 end', [UserRole::CHURCH_LEADER->value])
            ->first();

        abort_if($contact === null, 404, __('chat.no_contact'));

        $conversation = $this->directConversation($churchId, $actor, $contact);

        return response()->json(['conversation' => $this->conversationData($conversation, $actor)]);
    }

    /**
     * Open (creating if needed) a DIRECT thread between the member and a staff
     * member of the church.
     */
    public function openDirect(Request $request): JsonResponse
    {
        $churchId = $this->churchId($request);
        abort_unless(is_string($churchId) && $churchId !== '', 422, __('church.context.required'));

        $validated = $request->validate([
            'recipient_id' => ['required', 'string', 'exists:users,id'],
        ]);

        $recipient = User::query()->findOrFail($validated['recipient_id']);
        $actor = $request->user();

        // A member may only reach church staff; staff may reach anyone in the church.
        $actorIsStaff = in_array($actor->role, ChatAccess::STAFF_ROLES, true)
            || in_array($actor->role, ChatAccess::MODERATOR_ROLES, true);
        $recipientIsStaff = in_array($recipient->role, ChatAccess::STAFF_ROLES, true)
            || in_array($recipient->role, ChatAccess::MODERATOR_ROLES, true);

        abort_unless($actorIsStaff || $recipientIsStaff, 403);
        abort_unless(
            $this->churchAccess->canReachChurch($actor, $churchId)
                && ChurchAccess::homeChurchId($recipient) === $churchId,
            403,
        );
        abort_if($recipient->id === $actor->id, 422);

        $conversation = $this->directConversation($churchId, $actor, $recipient);

        return response()->json(['conversation' => $this->conversationData($conversation, $actor)]);
    }

    private function directConversation(string $churchId, User $a, User $b): Conversation
    {
        $existing = Conversation::query()
            ->where('church_id', $churchId)
            ->where('type', ConversationType::DIRECT->value)
            ->whereHas('participants', fn ($query) => $query->whereKey($a->id))
            ->whereHas('participants', fn ($query) => $query->whereKey($b->id))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $conversation = Conversation::query()->create([
            'church_id' => $churchId,
            'type' => ConversationType::DIRECT->value,
            'last_message_at' => Carbon::now(),
        ]);
        $conversation->participants()->syncWithoutDetaching([$a->id, $b->id]);

        return $conversation;
    }

    private function conversationForContext(Model $context): Conversation
    {
        return Conversation::query()->firstOrCreate(
            [
                'conversable_type' => $context::class,
                'conversable_id' => $context->getKey(),
            ],
            [
                'church_id' => $context->church_id,
                'type' => ConversationType::CONTEXT->value,
                'last_message_at' => Carbon::now(),
            ],
        );
    }

    private function resolveContext(string $contextType, string $contextId): Model
    {
        $modelClass = self::CONTEXT_MODELS[$contextType] ?? abort(404);

        return $modelClass::query()->findOrFail($contextId);
    }

    private function attachmentType(string $mime): ChatMessageType
    {
        return match (true) {
            str_starts_with($mime, 'image/') => ChatMessageType::IMAGE,
            str_starts_with($mime, 'video/') => ChatMessageType::VIDEO,
            str_starts_with($mime, 'audio/') => ChatMessageType::AUDIO,
            default => ChatMessageType::TEXT,
        };
    }

    private function firstUrl(string $body): ?string
    {
        return preg_match('/https?:\/\/[^\s]+/i', $body, $match) ? $match[0] : null;
    }

    private function churchId(Request $request): ?string
    {
        return $this->context->churchId() ?? ChurchAccess::homeChurchId($request->user());
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationData(Conversation $conversation, ?User $user): array
    {
        $context = $conversation->conversable;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'title' => $this->conversationTitle($conversation, $user),
            'context' => $context !== null ? [
                'type' => strtolower(class_basename($context::class)),
                'id' => $context->getKey(),
                'chat_enabled' => (bool) ($context->chat_enabled ?? false),
            ] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'can_moderate' => $this->chat->canModerate($user, $conversation),
            'can_post' => $this->chat->canPost($user, $conversation),
        ];
    }

    private function conversationTitle(Conversation $conversation, ?User $user): string
    {
        $context = $conversation->conversable;

        if ($context !== null) {
            return (string) ($context->title ?? $context->name ?? __('chat.conversation'));
        }

        $other = $conversation->participants->firstWhere('id', '!=', $user?->id);

        return $other !== null ? trim($other->first_name.' '.$other->last_name) : __('chat.direct');
    }

    /**
     * @return array<string, mixed>
     */
    private function messageData(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'type' => $message->type->value,
            'body' => $message->body,
            'attachment_url' => $message->attachment_url,
            'attachment_mime' => $message->attachment_mime,
            'link_preview' => $message->link_preview,
            'created_at' => $message->created_at?->toIso8601String(),
            'user' => $message->user?->only(['id', 'first_name', 'last_name']),
            'is_mine' => $message->user_id === request()->user()?->id,
        ];
    }
}
