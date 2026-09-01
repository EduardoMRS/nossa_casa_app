<?php

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\Library;
use App\Models\Media;
use App\Models\Post;
use App\Support\ChurchDomainContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentEmbedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $churchId = app(ChurchDomainContext::class)->churchId() ?: $request->user()?->church?->id;
        abort_unless($churchId, 403);

        return response()->json([
            'forms' => Form::query()->where('church_id', $churchId)->orderBy('title')->get(['id', 'title', 'description']),
            'media' => Media::query()->with('categories:id,name')->where('church_id', $churchId)->where('status', MediaStatus::APPROVED)->latest()->get()->map(fn (Media $media): array => [
                'id' => $media->id, 'title' => $media->title ?: basename($media->file_path),
                'description' => $media->description, 'url' => $media->url, 'mimetype' => $media->mimetype,
                'categories' => $media->categories->map->only(['id', 'name'])->values(),
            ]),
            'posts' => Post::query()->where('church_id', $churchId)->where('visibility', 'public')->latest()->get(['id', 'title', 'slug', 'content']),
            'events' => Event::query()->where('church_id', $churchId)->latest('start_time')->get(['id', 'title', 'slug', 'description', 'start_time']),
            'library' => Library::query()->with('categories:id,name')->where('church_id', $churchId)->latest()->get()->map(fn (Library $item): array => [
                'id' => $item->id, 'title' => $item->title, 'description' => $item->description,
                'type' => $item->type, 'url' => $item->file_url,
                'categories' => $item->categories->map->only(['id', 'name'])->values(),
            ]),
        ]);
    }
}
