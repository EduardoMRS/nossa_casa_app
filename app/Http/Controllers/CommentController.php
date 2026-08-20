<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\Event;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Comment::with('user:id,first_name,last_name')
            ->when($request->commentable_type, fn ($query, $type) => $query->where('commentable_type', $this->commentableClass($type)))
            ->when($request->commentable_id, fn ($query, $id) => $query->where('commentable_id', $id))
            ->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'commentable_type' => ['required', Rule::in(['post', 'event', 'media', 'live_stream', 'comment'])],
            'commentable_id' => ['required', 'string'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $commentable = $this->findCommentable($validated['commentable_type'], $validated['commentable_id']);
        $this->ensurePublicChurchResource($this->commentableChurchId($commentable));

        $comment = Comment::create([
            ...$validated,
            'commentable_type' => $this->commentableClass($validated['commentable_type']),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($comment, 201);
    }

    public function update(Request $request, Comment $comment)
    {
        $churchId = $this->commentableChurchId($comment);
        $this->ensurePublicChurchResource($churchId);
        $this->ensureOwnerOrChurchModerator($request, $comment->user_id, $churchId);
        $comment->update($request->validate(['content' => ['required', 'string', 'max:5000']]));

        return response()->json($comment);
    }

    public function destroy(Request $request, Comment $comment)
    {
        $churchId = $this->commentableChurchId($comment);
        $this->ensurePublicChurchResource($churchId);
        $this->ensureOwnerOrChurchModerator($request, $comment->user_id, $churchId);
        $comment->delete();

        return response()->noContent();
    }

    public function pin(Request $request, Comment $comment): JsonResponse
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, [UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM], true), 403);

        $this->ensureChurchAccess($request, $this->commentableChurchId($comment));
        $validated = $request->validate(['is_pinned' => ['required', 'boolean']]);

        $comment->update([
            'is_pinned' => $validated['is_pinned'],
            'pinned_by_id' => $validated['is_pinned'] ? $request->user()->id : null,
            'pinned_at' => $validated['is_pinned'] ? now() : null,
        ]);

        return response()->json($comment->fresh('user:id,first_name,last_name'));
    }

    private function commentableClass(string $type): string
    {
        return match ($type) {
            'post' => Post::class,
            'event' => Event::class,
            'media' => Media::class,
            'live_stream' => LiveStream::class,
            'comment' => Comment::class,
        };
    }

    private function findCommentable(string $type, string $id): Post|Event|Media|LiveStream|Comment
    {
        return $this->commentableClass($type)::query()->findOrFail($id);
    }

    private function commentableChurchId(Post|Event|Media|LiveStream|Comment $commentable): string
    {
        if ($commentable instanceof Comment) {
            $parent = $commentable->commentable;

            abort_unless($parent instanceof Post || $parent instanceof Event || $parent instanceof Media || $parent instanceof LiveStream || $parent instanceof Comment, 422);

            return $this->commentableChurchId($parent);
        }

        abort_unless($commentable->church_id, 422);

        return $commentable->church_id;
    }
}
