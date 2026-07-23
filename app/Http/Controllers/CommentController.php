<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Media;
use App\Models\Post;
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
            'commentable_type' => ['required', Rule::in(['post', 'event', 'media', 'comment'])],
            'commentable_id' => ['required', 'string'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $comment = Comment::create([
            ...$validated,
            'commentable_type' => $this->commentableClass($validated['commentable_type']),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($comment, 201);
    }

    public function update(Request $request, Comment $comment)
    {
        $this->ensureOwnerOrModerator($request, $comment->user_id);
        $comment->update($request->validate(['content' => ['required', 'string', 'max:5000']]));

        return response()->json($comment);
    }

    public function destroy(Request $request, Comment $comment)
    {
        $this->ensureOwnerOrModerator($request, $comment->user_id);
        $comment->delete();

        return response()->noContent();
    }

    private function commentableClass(string $type): string
    {
        return match ($type) {
            'post' => Post::class,
            'event' => Event::class,
            'media' => Media::class,
            'comment' => Comment::class,
        };
    }
}
