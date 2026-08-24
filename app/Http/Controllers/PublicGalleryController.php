<?php

namespace App\Http\Controllers;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Queries\MediaQuery;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicGalleryController extends Controller
{
    public function __construct(
        private readonly ChurchDomainContext $context,
        private readonly MediaQuery $media,
    ) {}

    public function index(Request $request): Response
    {
        $churchId = $this->context->churchId();

        return Inertia::render('Gallery/Index', $this->media->index(
            $churchId,
            $request->string('view')->toString(),
            $request->user() !== null && $churchId !== null,
        )->toArray());
    }

    public function download(Media $media): StreamedResponse
    {
        $churchId = $this->context->churchId();
        abort_unless($media->status === MediaStatus::APPROVED && $media->gallery, 404);
        abort_if($churchId && $media->church_id !== $churchId, 404);

        return Storage::disk($media->disk ?: (string) config('media.disk'))
            ->download($media->file_path, basename($media->file_path));
    }
}
