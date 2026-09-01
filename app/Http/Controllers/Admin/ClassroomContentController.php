<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassroomActivity;
use App\Models\ClassroomDiscussion;
use App\Models\ClassroomMaterial;
use App\Models\Form;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomContentController extends Controller
{
    public function index(Request $request, Classroom $classroom): Response
    {
        $this->ensureManage($request, $classroom);
        $classroom->load(['posts' => fn ($query) => $query->where('visibility', 'classroom_private')->latest(), 'activities.form', 'materials', 'discussions.author']);
        return Inertia::render('Admin/ClassroomContent', [
            'classroom' => $classroom,
            'forms' => Form::query()->where('church_id', $classroom->church_id)->orderBy('title')->get(['id', 'title', 'description']),
            'portalUrl' => route('classrooms.show', ['classroom' => $classroom->slug]),
        ]);
    }

    public function updateSettings(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'portal_enabled' => ['required', 'boolean'],
            'forum_enabled' => ['required', 'boolean'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ]);
        $coverPath = $classroom->cover_path;
        if ($request->hasFile('cover')) {
            if ($coverPath) Storage::disk((string) config('media.disk'))->delete($coverPath);
            $coverPath = $request->file('cover')->store("church/{$classroom->church_id}/classrooms/{$classroom->id}", (string) config('media.disk'));
        }
        $classroom->update([
            ...collect($validated)->except(['cover', 'forum_enabled'])->all(),
            'cover_path' => $coverPath,
            'portal_settings' => [...($classroom->portal_settings ?? []), 'forum_enabled' => $validated['forum_enabled']],
        ]);
        return back()->with('success', __('Classroom portal updated.'));
    }

    public function storePost(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'comments_enabled' => ['required', 'boolean'],
            'reactions_enabled' => ['required', 'boolean'],
        ]);
        $post = Post::query()->create([
            ...$validated,
            'slug' => Str::slug($validated['title']).'-'.Str::lower((string) Str::ulid()),
            'author_id' => $request->user()->id,
            'church_id' => $classroom->church_id,
            'published_at' => $validated['published_at'] ?? now(),
            'visibility' => 'classroom_private',
        ]);
        $classroom->posts()->attach($post);
        return back()->with('success', __('Post published.'));
    }

    public function updatePost(Request $request, Classroom $classroom, Post $post): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        abort_unless($classroom->posts()->whereKey($post->id)->exists() && $post->visibility === 'classroom_private', 404);
        $post->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'comments_enabled' => ['required', 'boolean'],
            'reactions_enabled' => ['required', 'boolean'],
        ]));
        return back()->with('success', __('Post updated.'));
    }

    public function destroyPost(Request $request, Classroom $classroom, Post $post): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        abort_unless($classroom->posts()->whereKey($post->id)->exists(), 404);
        $post->delete();
        return back();
    }

    public function storeActivity(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        $validated = $request->validate([
            'form_id' => ['required', Rule::exists('forms', 'id')->where('church_id', $classroom->church_id)],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'published_at' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:published_at'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'is_published' => ['required', 'boolean'],
        ]);
        $classroom->activities()->create([...$validated, 'created_by_id' => $request->user()->id]);
        return back()->with('success', __('Activity created.'));
    }

    public function destroyActivity(Request $request, Classroom $classroom, ClassroomActivity $activity): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        abort_unless($activity->classroom_id === $classroom->id, 404);
        $activity->delete();
        return back();
    }

    public function storeMaterial(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['link', 'file'])],
            'url' => ['nullable', 'required_if:type,link', 'url:http,https', 'max:2000'],
            'file' => ['nullable', 'required_if:type,file', 'file', 'max:51200'],
        ]);
        $file = $request->file('file');
        $path = $file?->store("church/{$classroom->church_id}/classrooms/{$classroom->id}/materials", (string) config('media.disk'));
        $classroom->materials()->create([
            ...collect($validated)->except('file')->all(),
            'added_by_id' => $request->user()->id,
            'file_path' => $path,
            'disk' => $path ? (string) config('media.disk') : null,
            'mimetype' => $file?->getMimeType(),
            'size' => $file?->getSize(),
        ]);
        return back()->with('success', __('Material added.'));
    }

    public function destroyMaterial(Request $request, Classroom $classroom, ClassroomMaterial $material): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        abort_unless($material->classroom_id === $classroom->id, 404);
        if ($material->file_path) Storage::disk($material->disk ?: (string) config('media.disk'))->delete($material->file_path);
        $material->delete();
        return back();
    }

    public function moderateDiscussion(Request $request, Classroom $classroom, ClassroomDiscussion $discussion): JsonResponse
    {
        $this->ensureManage($request, $classroom);
        abort_unless($discussion->classroom_id === $classroom->id, 404);
        $validated = $request->validate(['is_pinned' => ['required', 'boolean'], 'is_locked' => ['required', 'boolean']]);
        $discussion->update([...$validated, 'pinned_by_id' => $validated['is_pinned'] ? $request->user()->id : null]);
        return response()->json($discussion);
    }

    public function destroyDiscussion(Request $request, Classroom $classroom, ClassroomDiscussion $discussion): RedirectResponse
    {
        $this->ensureManage($request, $classroom);
        abort_unless($discussion->classroom_id === $classroom->id, 404);
        $discussion->delete();
        return back();
    }

    private function ensureManage(Request $request, Classroom $classroom): void
    {
        $this->ensurePublicChurchResource($classroom->church_id);
        abort_unless($request->user()->can('manage', $classroom), 403);
    }
}
