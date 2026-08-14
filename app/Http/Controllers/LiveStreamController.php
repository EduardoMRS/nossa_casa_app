<?php

namespace App\Http\Controllers;

use App\Actions\Media\CreateLiveStream;
use App\Actions\Media\RotateLiveStreamToken;
use App\Actions\Media\StopLiveStream;
use App\Enums\UserRole;
use App\Http\Requests\Media\StoreLiveStreamRequest;
use App\Models\LiveStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LiveStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $churchId = $request->user()?->church()->first()?->getKey();

        $liveStreams = LiveStream::query()
            ->when(
                $request->user()?->role !== UserRole::SYSTEM,
                fn ($query) => $query->where('church_id', $churchId),
            )
            ->withCount('recordings')
            ->latest()
            ->paginate();

        return response()->json($liveStreams);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLiveStreamRequest $request, CreateLiveStream $createLiveStream): JsonResponse
    {
        $liveStream = $createLiveStream->handle($request->user(), [
            'name' => (string) $request->validated('name'),
            'source_url' => (string) $request->validated('source_url'),
            'input_mode' => $request->validated('mode')
                ?? ($request->filled('source_url') ? 'pull' : 'publisher'),
            'source_on_demand' => $request->boolean('source_on_demand'),
            'record' => $request->boolean('record', true),
            'is_public' => $request->boolean('is_public', true),
            'church_id' => $request->validated('church_id'),
        ]);

        return response()->json($liveStream, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, LiveStream $liveStream): JsonResponse
    {
        $this->ensureAccess($request, $liveStream);

        return response()->json($liveStream->load('recordings'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, LiveStream $liveStream, StopLiveStream $stopLiveStream): Response
    {
        $this->ensureAccess($request, $liveStream);
        $stopLiveStream->handle($liveStream);

        return response()->noContent();
    }

    public function rotateToken(Request $request, LiveStream $liveStream, RotateLiveStreamToken $rotateToken): JsonResponse
    {
        $this->ensureAccess($request, $liveStream);
        $token = $rotateToken->handle($liveStream);

        return response()->json([
            'token' => $token,
            'token_rotated_at' => $liveStream->refresh()->token_rotated_at,
        ]);
    }

    private function ensureAccess(Request $request, LiveStream $liveStream): void
    {
        $churchId = $request->user()?->church()->first()?->getKey();

        abort_unless(
            $request->user()?->role === UserRole::SYSTEM
                || $liveStream->church_id === $churchId,
            403,
        );
    }
}
