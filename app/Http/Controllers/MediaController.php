<?php

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function index()
    {
        return response()->json(Media::visible()->with('uploader:id,first_name,last_name')->paginate(15));
    }

    public function show(Media $media)
    {
        abort_unless($media->status === MediaStatus::APPROVED, 404);

        return response()->json($media->load(['categories', 'comments.user']));
    }

    public function store(Request $request)
    {
        $file = $request->file('file');
        $validated = $request->validate([
            'file_path' => ['required', 'string', 'max:255', 'default' => $file?->store('media')],
            'mimetype' => ['required', 'string', 'max:255', 'default' => $file?->getClientMimeType()],
            'size' => ['required', 'integer', 'min:1', 'default' => $file?->getSize()],
            'gallery' => ['boolean'],
        ]);
        $church = $request->user()->church;

        abort_unless($church, 422, 'A church membership is required to upload media.');

        $media = Media::create([
            ...$validated,
            'uploader_id' => $request->user()->id,
            'church_id' => $church->id,
            'status' => MediaStatus::PENDING,
        ]);

        return response()->json($media, 201);
    }

    public function update(Request $request, Media $media)
    {
        $this->ensureOwnerOrModerator($request, $media->uploader_id);
        $media->update($request->validate([
            'gallery' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(MediaStatus::class)],
        ]));

        return response()->json($media);
    }

    public function destroy(Request $request, Media $media)
    {
        $this->ensureOwnerOrModerator($request, $media->uploader_id);
        $media->delete();

        return response()->noContent();
    }
}
