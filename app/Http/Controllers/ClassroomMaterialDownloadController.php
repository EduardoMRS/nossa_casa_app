<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassroomMaterialDownloadController extends Controller
{
    public function __invoke(Classroom $classroom, ClassroomMaterial $material): RedirectResponse|StreamedResponse
    {
        abort_unless($material->classroom_id === $classroom->id, 404);
        $this->ensurePublicChurchResource($classroom->church_id);
        abort_unless(request()->user()->can('view', $classroom), 403);

        if ($material->type === 'link') return redirect()->away($material->url);

        abort_unless($material->file_path, 404);
        $disk = $material->disk ?: (string) config('media.disk');
        abort_unless(Storage::disk($disk)->exists($material->file_path), 404);
        return Storage::disk($disk)->download($material->file_path, basename($material->file_path));
    }
}
