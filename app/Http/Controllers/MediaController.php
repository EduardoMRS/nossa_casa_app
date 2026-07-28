<?php

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\UploadsMedia;
class MediaController extends Controller
{
    use UploadsMedia;

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
        $validated = $request->validate([
            'file_path' => ['nullable'],
            'file'      => ['nullable'], // Permite receber na chave 'file' também se for multipart
            'mimetype'  => ['nullable', 'string', 'max:255'],
            'size'      => ['nullable', 'integer', 'min:0'],
            'gallery'   => ['boolean'],
        ]);
        
        $church = $request->user()->church;
        abort_unless($church && $church->exists(), 422, __('church.membership_upload_media_required'));

        // Extrai o arquivo tentando de 'file' (upload) ou 'file_path' (string)
        $fileInput = $request->file('file') ?? $request->file('file_path') ?? $request->input('file_path');
        if(!$fileInput) {
            return response()->json(['error' => 'No file provided.'], 422);
        }
        
        $mediaPath = $this->handleMediaUpload($fileInput, "church/{$church->id}/media");

        $media = Media::create([
            'file_path' => $mediaPath,
            'mimetype' => $validated['mimetype'] ?? null,
            'size' => $validated['size'] ?? null,
            'gallery' => $validated['gallery'] ?? false,
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

    public function pending()
    {
        return response()->json(Media::pending()->with('uploader:id,first_name,last_name')->paginate(15));
    }

    public function updateStatus(Request $request, Media $media)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(MediaStatus::class)],
        ]);

        $media->update(['status' => $validated['status']]);

        return response()->json($media);
    }
}
