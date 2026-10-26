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
        $user = $request->user();
        $isGlobalAdministrator = $user?->role === UserRole::SYSTEM;
        $managerRoles = [UserRole::MEDIA, UserRole::CHURCH_LEADER];
        $requestedChurchId = $request->string('church_id')->toString();
        // A requested church is honored only inside the user's scope
        // (own church or branches); SYSTEM may pick any church.
        $church = $requestedChurchId !== ''
            && $this->churchAccess()->canManageChurch($user, $requestedChurchId, $managerRoles)
            ? Church::query()->findOrFail($requestedChurchId)
            : $this->domainContext->church();

        $church ??= $user?->church;
        $church ??= $isGlobalAdministrator ? Church::query()->orderBy('name')->first() : null;

        abort_unless($church instanceof Church, 422, __('church.context_required'));
        abort_unless($this->churchAccess()->canManageChurch($user, $church->id, $managerRoles), 403);
        $scopeChurchIds = $this->churchAccess()->scopeChurchIds($user);

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
                : (count($scopeChurchIds) > 1
                    ? Church::query()->whereIn('id', $scopeChurchIds)->orderBy('name')->get(['id', 'name'])
                    : []),
            'streams' => $streams,
            'canCreate' => $streams->where('active', true)->count() < 2,
            'canDelete' => $this->churchAccess()->canManageChurch($user, $church->id, [UserRole::CHURCH_LEADER]),
        ]);
    }

    /** @return array<string, mixed> */
    private function streamItem(Church $church, LiveStream $liveStream): array
    {
        $token = $liveStream->input_mode === 'publisher' ? (string) $liveStream->publish_token : null;
        $ingestBaseUrl = $church->rtmp_url;
        $streamKey = $token
            ? $liveStream->path.'?token='.rawurlencode($token)
            : null;

        return [
            'id' => $liveStream->id,
            'name' => $liveStream->name,
            'status' => $liveStream->status->value,
            'active' => $liveStream->active_slot !== null,
            'input_mode' => $liveStream->input_mode,
            'record' => $liveStream->record,
            'is_public' => $liveStream->is_public,
            'ends_on_disconnect' => $liveStream->ends_on_disconnect,
            'started_at' => $liveStream->started_at,
            'ended_at' => $liveStream->ended_at,
            'recordings_count' => $liveStream->recordings_count,
            'token' => $token,
            'token_rotated_at' => $liveStream->token_rotated_at,
            'ingest_server' => $token ? $ingestBaseUrl : null,
            'stream_key' => $streamKey,
            'ingest_url' => $streamKey ? $ingestBaseUrl.'/'.$streamKey : null,
            'public_url' => $this->domainContext->churchUrl($church, '/live-streams/'.$liveStream->id),
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
