<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMaterial;
use App\Models\Form;
use App\Models\Media;
use App\Models\Post;
use App\Traits\ManagesChurchCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventContentController extends Controller
{
    use ManagesChurchCategories;

    public function index(Request $request, Event $event): Response
    {
        $this->ensureCanManage($request, $event);
        $event->load(['privatePosts', 'materials', 'medias']);

        return Inertia::render('Admin/EventContent', [
            'event' => $event->only(['id', 'title', 'slug']),
            'posts' => $event->privatePosts->map(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'published_at' => $post->published_at,
                'updated_at' => $post->updated_at,
            ]),
            'materials' => $event->materials->map(fn (EventMaterial $material): array => [
                'id' => $material->id,
                'title' => $material->title,
                'type' => $material->type,
                'url' => $material->download_url,
            ]),
            'linkedMediaIds' => $event->medias->modelKeys(),
            'availableMedia' => Media::query()
                ->where('church_id', $event->church_id)
                ->where('status', MediaStatus::APPROVED)
                ->latest()
                ->get()
                ->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'title' => $media->title ?: basename($media->file_path),
                    'url' => $media->url,
                    'mimetype' => $media->mimetype,
                    'gallery' => $media->gallery,
                ]),
        ]);
    }

    public function createPost(Request $request, Event $event): Response
    {
        $this->ensureCanManage($request, $event);

        return Inertia::render('Posts/Form', $this->postFormProps($event));
    }

    public function editPost(Request $request, Event $event, Post $post): Response
    {
        $this->ensureCanManage($request, $event);
        abort_unless($event->privatePosts()->whereKey($post->id)->exists(), 404);

        return Inertia::render('Posts/Form', $this->postFormProps($event, $post));
    }

    public function storePost(Request $request, Event $event): JsonResponse
    {
        $this->ensureCanManage($request, $event);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'form_id' => ['nullable', 'string', 'exists:forms,id'],
        ]);
        $post = Post::query()->create([
            ...collect($validated)->except('form_id')->all(),
            'slug' => $validated['slug'] ?: Str::slug($validated['title']).'-'.Str::lower((string) Str::ulid()),
            'author_id' => $request->user()->id,
            'church_id' => $event->church_id,
            'published_at' => $validated['published_at'] ?? now(),
            'is_event_private' => true,
        ]);
        $event->privatePosts()->attach($post);
        $post->categories()->sync($this->syncChurchCategories($request, CategoryType::POST->value, $event->church_id));
        $this->syncPostForm($post, $validated['form_id'] ?? null, $event->church_id);

        if ($request->header('X-Inertia')) {
            return redirect()->route('admin.events.content.index', $event);
        }

        return response()->json($post, 201);
    }

    public function updatePost(Request $request, Event $event, Post $post): JsonResponse|RedirectResponse
    {
        $this->ensureCanManage($request, $event);
        abort_unless($event->privatePosts()->whereKey($post->id)->exists(), 404);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post->id)],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'form_id' => ['nullable', 'string', 'exists:forms,id'],
        ]);
        $post->update(collect($validated)->except('form_id')->all());
        $post->categories()->sync($this->syncChurchCategories($request, CategoryType::POST->value, $event->church_id));
        $this->syncPostForm($post, $validated['form_id'] ?? null, $event->church_id);

        if ($request->header('X-Inertia')) {
            return redirect()->route('admin.events.content.index', $event);
        }

        return response()->json($post);
    }

    public function destroyPost(Request $request, Event $event, Post $post): HttpResponse
    {
        $this->ensureCanManage($request, $event);
        abort_unless($event->privatePosts()->whereKey($post->id)->exists(), 404);
        $post->delete();

        return response()->noContent();
    }

    public function storeMaterial(Request $request, Event $event): JsonResponse
    {
        $this->ensureCanManage($request, $event);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['link', 'file'])],
            'url' => ['nullable', 'required_if:type,link', 'url:http,https', 'max:2000'],
            'file' => ['nullable', 'required_if:type,file', 'file', 'max:51200'],
        ]);
        $file = $request->file('file');
        $path = $file?->store("church/{$event->church_id}/events/{$event->id}/materials", (string) config('media.disk'));
        $material = $event->materials()->create([
            'added_by_id' => $request->user()->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'url' => $validated['type'] === 'link' ? $validated['url'] : null,
            'file_path' => $path,
            'disk' => $path ? (string) config('media.disk') : null,
            'mimetype' => $file?->getMimeType(),
            'size' => $file?->getSize(),
        ]);

        return response()->json($material, 201);
    }

    public function destroyMaterial(Request $request, Event $event, EventMaterial $material): HttpResponse
    {
        $this->ensureCanManage($request, $event);
        abort_unless($material->event_id === $event->id, 404);

        if ($material->file_path) {
            Storage::disk($material->disk ?: (string) config('media.disk'))->delete($material->file_path);
        }

        $material->delete();

        return response()->noContent();
    }

    public function syncMedia(Request $request, Event $event): JsonResponse
    {
        $this->ensureCanManage($request, $event);
        $validated = $request->validate([
            'media_ids' => ['present', 'array'],
            'media_ids.*' => ['string', 'distinct', 'exists:medias,id'],
        ]);
        $mediaIds = Media::query()
            ->where('church_id', $event->church_id)
            ->where('status', MediaStatus::APPROVED)
            ->whereIn('id', $validated['media_ids'])
            ->pluck('id');
        $event->medias()->sync($mediaIds);

        return response()->json(['media_ids' => $mediaIds]);
    }

    private function ensureCanManage(Request $request, Event $event): void
    {
        $user = $request->user();
        $isGlobalAdministrator = in_array($user?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);
        $isChurchLeader = $user?->role === UserRole::CHURCH_LEADER
            && $user->profile?->church_id === $event->church_id;
        $isResponsible = $event->author_id === $user?->id
            || $event->responsibleUsers()->whereKey($user?->id)->exists();

        abort_unless($isGlobalAdministrator || $isChurchLeader || $isResponsible, 403);
    }

    /** @return array<string, mixed> */
    private function postFormProps(Event $event, ?Post $post = null): array
    {
        return [
            'privateEvent' => $event->only(['id', 'title']),
            'returnUrl' => route('admin.events.content.index', $event),
            'categories' => $event->church->categories()->where('type', CategoryType::POST->value)->get(['id', 'name', 'slug', 'type']),
            'forms' => Form::query()->where('church_id', $event->church_id)->orderBy('title')->get(['id', 'title', 'description']),
            'post' => $post ? [
                ...$post->only(['id', 'title', 'slug', 'content', 'published_at', 'expires_at']),
                'category_ids' => $post->categories()->pluck('categories.id')->all(),
                'form_id' => $post->forms()->value('forms.id'),
            ] : null,
        ];
    }

    private function syncPostForm(Post $post, mixed $formId, string $churchId): void
    {
        if (blank($formId)) {
            $post->forms()->detach();

            return;
        }

        abort_unless(Form::query()->whereKey($formId)->where('church_id', $churchId)->exists(), 422);
        $post->forms()->sync([$formId]);
    }
}
