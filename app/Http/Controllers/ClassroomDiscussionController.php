<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomDiscussion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomDiscussionController extends Controller
{
    public function store(Request $request, Classroom $classroom): JsonResponse
    {
        $this->ensurePublicChurchResource($classroom->church_id);
        abort_unless($request->user()->can('participateInForum', $classroom), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:10000'],
        ]);
        $discussion = $classroom->discussions()->create([
            ...$validated,
            'author_id' => $request->user()->id,
            'last_activity_at' => now(),
        ]);
        return response()->json($discussion->load('author:id,first_name,last_name'), 201);
    }

    public function reply(Request $request, Classroom $classroom, ClassroomDiscussion $discussion): JsonResponse
    {
        abort_unless($discussion->classroom_id === $classroom->id, 404);
        $this->ensurePublicChurchResource($classroom->church_id);
        abort_unless($request->user()->can('participateInForum', $classroom), 403);
        abort_if($discussion->is_locked, 423);
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'string', 'exists:classroom_discussion_replies,id'],
        ]);
        if (! empty($validated['parent_id'])) {
            abort_unless($discussion->replies()->whereKey($validated['parent_id'])->exists(), 422);
        }
        $reply = $discussion->replies()->create([
            ...$validated,
            'author_id' => $request->user()->id,
        ]);
        $discussion->update(['last_activity_at' => now()]);
        return response()->json($reply->load('author:id,first_name,last_name'), 201);
    }
}
