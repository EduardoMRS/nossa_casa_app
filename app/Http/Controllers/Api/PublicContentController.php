<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CanonicalResource;
use App\Models\LiveStream;
use App\Queries\EventQuery;
use App\Queries\LibraryQuery;
use App\Queries\LiveStreamQuery;
use App\Queries\MediaQuery;
use App\Queries\PortalQuery;
use App\Queries\PostQuery;
use App\Support\ChurchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PublicContentController extends Controller
{
    public function __construct(private readonly ChurchContext $context) {}

    public function portal(Request $request, PortalQuery $query): JsonResponse
    {
        $church = $this->context->church();
        abort_unless($church, 404);

        return $this->respond($request, $query->churchHome($church, $request->user()));
    }

    public function posts(Request $request, PostQuery $query): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:26'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'sort' => ['nullable', 'in:latest,popular'],
        ]);

        return $this->respond($request, $query->index($filters, $this->context->churchId()));
    }

    public function post(Request $request, string $slug, PostQuery $query): JsonResponse
    {
        return $this->respond($request, $query->show($slug, $this->context->churchId(), $request->user()));
    }

    public function events(Request $request, EventQuery $query): JsonResponse
    {
        return $this->respond($request, $query->index($this->context->churchId()));
    }

    public function event(Request $request, string $slug, EventQuery $query): JsonResponse
    {
        return $this->respond($request, $query->show($slug, $this->context->churchId(), $request->user()));
    }

    public function gallery(Request $request, MediaQuery $query): JsonResponse
    {
        return $this->respond($request, $query->index(
            $this->context->churchId(),
            $request->string('view')->toString(),
            $request->user() !== null && $this->context->churchId() !== null,
        ));
    }

    public function library(Request $request, LibraryQuery $query): JsonResponse
    {
        return $this->respond($request, $query->index($this->context->church()));
    }

    public function bible(Request $request, LibraryQuery $query): JsonResponse
    {
        $church = $this->context->church();
        abort_unless($church, 404);

        return $this->respond($request, $query->bible($church));
    }

    public function liveStream(Request $request, LiveStream $liveStream, LiveStreamQuery $query): JsonResponse
    {
        $churchId = $this->context->churchId();
        abort_unless($churchId, 404);

        return $this->respond($request, $query->show($liveStream, $churchId, $request->user()));
    }

    private function respond(Request $request, mixed $data): JsonResponse
    {
        return response()->json((new CanonicalResource($data))->resolve($request));
    }
}
