<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\LiveStream;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveStreamControlController extends Controller
{
    public function __construct(private readonly ChurchDomainContext $domainContext) {}

    public function index(Request $request): Response
    {
        $isGlobalAdministrator = in_array($request->user()?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true);
        $church = $isGlobalAdministrator && $request->filled('church_id')
            ? Church::query()->findOrFail($request->string('church_id')->toString())
            : $this->domainContext->church();

        $church ??= $request->user()?->church;
        $church ??= $isGlobalAdministrator ? Church::query()->orderBy('name')->first() : null;

        abort_unless($church instanceof Church, 422, __('church.context_required'));
        abort_unless(
            $isGlobalAdministrator
                || $request->user()?->profile?->church_id === $church->id,
            403,
        );

        $streams = LiveStream::query()
            ->where('church_id', $church->id)
            ->with(['recordings' => fn ($query) => $query->with('media:id,gallery')->latest()])
            ->withCount('recordings')
            ->latest()
            ->get()
            ->map(fn (LiveStream $liveStream): array => $this->streamItem($church, $liveStream));

        return Inertia::render('Admin/LiveStreams', [
            'church' => $church->only(['id', 'name']),
            'churches' => $isGlobalAdministrator
                ? Church::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'streams' => $streams,
            'canCreate' => ! $streams->contains(fn (array $stream): bool => $stream['active']),
        ]);
    }

    /** @return array<string, mixed> */
    private function streamItem(Church $church, LiveStream $liveStream): array
    {
        $token = $liveStream->input_mode === 'publisher' ? (string) $liveStream->publish_token : null;
        $ingestBaseUrl = rtrim((string) config('media.mediamtx.public_rtmp_url'), '/');
        $streamKey = $token
            ? $liveStream->path.'?token='.rawurlencode($token)
            : null;

        return [
            'id' => $liveStream->id,
            'name' => $liveStream->name,
            'status' => $liveStream->status->value,
            'active' => $liveStream->active_slot === 1,
            'input_mode' => $liveStream->input_mode,
            'record' => $liveStream->record,
            'is_public' => $liveStream->is_public,
            'started_at' => $liveStream->started_at,
            'ended_at' => $liveStream->ended_at,
            'recordings_count' => $liveStream->recordings_count,
            'token' => $token,
            'token_rotated_at' => $liveStream->token_rotated_at,
            'ingest_server' => $token ? $ingestBaseUrl : null,
            'stream_key' => $streamKey,
            'ingest_url' => $streamKey ? $ingestBaseUrl.'/'.$streamKey : null,
            'public_url' => $this->domainContext->churchUrl($church, '/transmissoes/'.$liveStream->id),
            'recordings' => $liveStream->recordings->map(fn ($recording): array => [
                'id' => $recording->id,
                'status' => $recording->status->value,
                'uploaded_at' => $recording->uploaded_at,
                'media_id' => $recording->media_id,
                'is_public' => (bool) $recording->media?->gallery,
            ]),
        ];
    }
}
