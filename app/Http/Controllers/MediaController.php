<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Requests\Media\UpdateMediaStatusRequest;
use App\Models\Media;
use App\Traits\ManagesChurchCategories;
use App\Traits\UploadsMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MediaController extends Controller
{
    use ManagesChurchCategories;
    use UploadsMedia;

    public function index()
    {
        return response()->json(Media::visible()->with('uploader:id,first_name,last_name')->paginate(15));
    }

    public function show(Media $media)
    {
        abort_unless($media->status === MediaStatus::APPROVED, 404);
        $media->load(['categories', 'comments.user']);
        $media->categories->each->localize();

        return response()->json($media);
    }

    public function store(StoreMediaRequest $request)
    {
        $validated = $request->validated();

        $church = $request->user()->church;
        abort_unless($church && $church->exists(), 422, __('church.membership_upload_media_required'));

        $fileInput = $request->file('file') ?? $request->file('file_path') ?? $request->input('file_path');

        if (! $fileInput) {
            return response()->json(['error' => 'No file provided.'], 422);
        }

        $fileData = getFileMetadata($fileInput);
        $mediaPath = $this->handleMediaUpload($fileInput, "church/{$church->id}/media");

        $media = Media::create([
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'file_path' => $mediaPath,
            'mimetype' => $validated['mimetype'] ?? ($fileData['mime_type'] ?? null),
            'size' => $validated['size'] ?? ($fileData['size'] ?? null),
            'gallery' => $validated['gallery'] ?? false,
            'uploader_id' => $request->user()->id,
            'church_id' => $church->id,
            'status' => MediaStatus::PENDING,
        ]);
        $media->categories()->sync($this->syncChurchCategories($request, CategoryType::MEDIA->value, $church->id));

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.media_submitted')]);

            return back();
        }

        return response()->json($media, 201);
    }

    public function update(UpdateMediaRequest $request, Media $media)
    {
        $this->ensureChurchAccess($request, $media->church_id);
        $this->ensureOwnerOrModerator($request, $media->uploader_id);
        $validated = $request->validated();

        $media->update(Arr::only($validated, [
            'title',
            'description',
            'gallery',
            'status',
        ]));

        if ($request->has('category_ids')) {
            $media->categories()->sync($this->syncChurchCategories($request, CategoryType::MEDIA->value, $media->church_id));
        }

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.media_updated')]);

            return back();
        }

        return response()->json($media);
    }

    public function destroy(Request $request, Media $media)
    {
        $this->ensureChurchAccess($request, $media->church_id);
        $this->ensureOwnerOrModerator($request, $media->uploader_id);

        if ($media->file_path) {
            Storage::disk('public')->delete($media->file_path);
        }

        $media->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.media_deleted')]);

            return back();
        }

        return response()->noContent();
    }

    public function pending(Request $request)
    {
        $churchId = $request->user()->church?->id;

        abort_unless($churchId, 422, 'A church membership is required to moderate media.');

        return response()->json(Media::pending()
            ->where('church_id', $churchId)
            ->with('uploader:id,first_name,last_name')
            ->paginate(15));
    }

    public function updateStatus(UpdateMediaStatusRequest $request, Media $media)
    {
        $this->ensureChurchAccess($request, $media->church_id);
        $validated = $request->validated();

        $media->update(['status' => $validated['status']]);

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.media_moderated')]);

            return back();
        }

        return response()->json($media);
    }
}
