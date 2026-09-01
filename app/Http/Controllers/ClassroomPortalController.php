<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Post;
use App\Support\ContentEmbedRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomPortalController extends Controller
{
    public function __construct(private readonly ContentEmbedRenderer $embedRenderer) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $classrooms = Classroom::query()
            ->with(['church:id,name,slug', 'teacher:id,first_name,last_name'])
            ->where('portal_enabled', true)
            ->where(function ($query) use ($user): void {
                $query->where('teacher_id', $user->id)
                    ->orWhereHas('members', fn ($members) => $members->whereKey($user->id));

                if ($user->role->value === 'system') {
                    $query->orWhereNotNull('id');
                } elseif (in_array($user->role->value, ['leader', 'church_leader', 'superadmin'], true)) {
                    $query->orWhere('church_id', $user->church?->id);
                }
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Classrooms/Index', ['classrooms' => $classrooms]);
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        $this->ensurePublicChurchResource($classroom->church_id);
        abort_unless($request->user()->can('view', $classroom), 403);

        $classroom->load(['church:id,name,slug', 'teacher:id,first_name,last_name']);

        $posts = $classroom->posts()
            ->where('visibility', 'classroom_private')
            ->published()
            ->with(['author:id,first_name,last_name', 'comments.user:id,first_name,last_name', 'comments.reactions.user:id,first_name,last_name', 'reactions.user:id,first_name,last_name'])
            ->latest('published_at')
            ->get()
            ->map(fn (Post $post): array => [
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'contentHtml' => $this->embedRenderer->render($post->content, $classroom->church_id),
                    'published_at' => $post->published_at,
                    'views_count' => $post->views_count,
                    'author' => $post->author_details,
                    'church' => $classroom->church?->only(['name']),
                    'metrics' => $post->metrics,
                ],
                'comments' => $post->comments,
                'reactions' => $post->reactions,
                'canComment' => $request->user()->can('comment', $post),
                'canReact' => $request->user()->can('react', $post),
            ]);

        $activities = $classroom->activities()
            ->with('form:id,title,description,schema')
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('available_until')->orWhere('available_until', '>', now()))
            ->orderByDesc('published_at')
            ->get()
            ->map(function ($activity) use ($request): array {
                $attempts = $activity->submissions()->where('user_id', $request->user()->id)->count();
                return [
                    ...$activity->only(['id', 'title', 'instructions', 'available_until', 'max_attempts']),
                    'form' => $activity->form,
                    'attempts' => $attempts,
                    'can_submit' => $attempts < $activity->max_attempts,
                ];
            });

        $materials = $classroom->materials()->latest()->get()->map(fn ($material): array => [
            ...$material->only(['id', 'title', 'description', 'type', 'mimetype', 'size']),
            'download_url' => route('classrooms.materials.download', [$classroom, $material]),
        ]);

        $discussions = $classroom->discussions()
            ->with(['author:id,first_name,last_name', 'replies.author:id,first_name,last_name'])
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->get();

        return Inertia::render('Classrooms/Portal', [
            'classroom' => [
                ...$classroom->only(['id', 'name', 'slug', 'description', 'accent_color', 'portal_settings']),
                'cover_url' => $classroom->cover_path ? genUrl($classroom->cover_path) : null,
                'church' => $classroom->church,
                'teacher' => $classroom->teacher,
            ],
            'wallPosts' => $posts,
            'activities' => $activities,
            'materials' => $materials,
            'discussions' => $discussions,
            'canManage' => $request->user()->can('manage', $classroom),
            'canUseForum' => $request->user()->can('participateInForum', $classroom),
        ]);
    }
}
