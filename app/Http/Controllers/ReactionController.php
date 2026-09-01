<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Media;
use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReactionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reactionable_type' => ['required', Rule::in(['post', 'event', 'media', 'comment'])],
            'reactionable_id' => ['required', 'string'],
            'content' => ['required', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        $reactionableClass = match ($validated['reactionable_type']) {
            'post' => Post::class, 'event' => Event::class,
            'media' => Media::class, 'comment' => Comment::class,
        };
        $reactionable = $reactionableClass::query()->findOrFail($validated['reactionable_id']);
        $churchId = $reactionable instanceof Comment ? $this->commentChurchId($reactionable) : $reactionable->church_id;
        $this->ensurePublicChurchResource($churchId);

        if ($post = $this->rootPost($reactionable)) Gate::authorize('react', $post);

        $reaction = Reaction::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'reactionable_type' => $reactionableClass, 'reactionable_id' => $reactionable->id],
            ['content' => $validated['content'], 'type' => $validated['type'] ?? 'emoji'],
        );
        return response()->json($reaction->load('user'), 201);
    }

    public function destroy(Request $request, Reaction $reaction): JsonResponse
    {
        abort_unless($reaction->user_id === $request->user()->id, 403);
        $reaction->delete();
        return response()->json(null, 204);
    }

    private function rootPost(Post|Event|Media|Comment $target): ?Post
    {
        if ($target instanceof Post) return $target;
        if ($target instanceof Comment) {
            $parent = $target->commentable;
            return $parent instanceof Post || $parent instanceof Comment ? $this->rootPost($parent) : null;
        }
        return null;
    }

    private function commentChurchId(Comment $comment): string
    {
        $commentable = $comment->commentable;
        if ($commentable instanceof Comment) return $this->commentChurchId($commentable);
        abort_unless($commentable instanceof Post || $commentable instanceof Event || $commentable instanceof Media, 422);
        return $commentable->church_id;
    }
}
