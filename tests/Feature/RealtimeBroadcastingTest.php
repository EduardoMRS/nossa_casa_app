<?php

use App\Enums\LiveStreamStatus;
use App\Events\LiveStreamCommentsUpdated;
use App\Events\LiveStreamUpdated;
use App\Events\SystemMetricsUpdated;
use App\Models\Church;
use App\Models\Comment;
use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;

test('public and private live streams use the matching realtime channel', function () {
    config(['media.mediamtx.public_webrtc_url' => 'https://media.example.test/webrtc']);
    $publicStream = LiveStream::factory()->create([
        'is_public' => true,
        'status' => LiveStreamStatus::LIVE,
    ]);
    $privateStream = LiveStream::factory()->create([
        'is_public' => false,
        'status' => LiveStreamStatus::LIVE,
    ]);

    $publicChannel = (new LiveStreamUpdated($publicStream))->broadcastOn()[0];
    $privateChannel = (new LiveStreamUpdated($privateStream))->broadcastOn()[0];

    expect($publicChannel)->toBeInstanceOf(Channel::class)
        ->not->toBeInstanceOf(PrivateChannel::class)
        ->and($publicChannel->name)->toBe('live-stream.'.$publicStream->id)
        ->and($privateChannel)->toBeInstanceOf(PrivateChannel::class)
        ->and($privateChannel->name)->toBe('private-live-stream.'.$privateStream->id)
        ->and($publicStream->playback_url)->toBe('https://media.example.test/webrtc/'.$publicStream->path)
        ->and($publicStream->playback_url)->not->toContain('.m3u8');
});

test('local mediamtx playback uses the same-origin WebRTC proxy', function () {
    config(['media.mediamtx.public_webrtc_url' => 'http://localhost']);
    $stream = LiveStream::factory()->create();

    expect($stream->playback_url)->toBe('/webrtc/'.$stream->path)
        ->and($stream->embed_url)->toStartWith('/webrtc/'.$stream->path.'?');
});

test('host-only mediamtx playback URLs include the WebRTC proxy path', function () {
    config(['media.mediamtx.public_webrtc_url' => 'https://media.example.test']);
    $stream = LiveStream::factory()->create();

    expect($stream->playback_url)->toBe('https://media.example.test/webrtc/'.$stream->path)
        ->and($stream->embed_url)->toStartWith('https://media.example.test/webrtc/'.$stream->path.'?');
});

test('live stream updates are queued so realtime outages do not fail management requests', function () {
    $stream = LiveStream::factory()->make();

    expect(LiveStreamUpdated::class)
        ->toImplement(ShouldBroadcast::class)
        ->not->toImplement(ShouldBroadcastNow::class)
        ->and((new LiveStreamUpdated($stream))->connection)
        ->toBe('database');
});

test('queued live stream updates keep their snapshot after the stream is deleted', function () {
    $stream = LiveStream::factory()->create([
        'is_public' => true,
        'status' => LiveStreamStatus::STOPPED,
    ]);
    $event = new LiveStreamUpdated($stream);

    $stream->delete();

    expect($event->broadcastOn()[0]->name)->toBe('live-stream.'.$stream->id)
        ->and($event->broadcastWith())
        ->toMatchArray([
            'id' => $stream->id,
            'status' => LiveStreamStatus::STOPPED->value,
        ]);
});

test('live chat broadcasts the canonical comment snapshot', function () {
    $stream = LiveStream::factory()->create(['is_public' => true]);
    $user = User::factory()->create();
    Event::fake([LiveStreamCommentsUpdated::class]);

    Comment::query()->create([
        'user_id' => $user->id,
        'commentable_type' => LiveStream::class,
        'commentable_id' => $stream->id,
        'content' => 'Realtime message',
    ]);

    $payload = (new LiveStreamCommentsUpdated($stream->id))->broadcastWith();

    expect($payload['comments'])->toHaveCount(1)
        ->and($payload['comments'][0]['content'])->toBe('Realtime message');
});

test('system logs and metrics are broadcast on an authenticated private channel', function () {
    $event = new SystemMetricsUpdated([
        'stats' => [],
        'queue' => ['pending' => 0, 'failed' => 0],
        'liveStreams' => [],
        'logs' => ['example'],
    ]);
    $channel = $event->broadcastOn()[0];

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-system.metrics')
        ->and($event->broadcastAs())->toBe('system.metrics.updated')
        ->and($event->broadcastWith()['logs'])->toBe(['example']);
});

test('broadcast authentication accepts Sanctum and enforces live stream church membership', function () {
    $church = Church::factory()->create();
    $stream = LiveStream::factory()->create([
        'church_id' => $church->id,
        'is_public' => false,
    ]);
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $church->assignMember($member);
    expect($member->churches()->whereKey($church->id)->exists())->toBeTrue();
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    $memberToken = $member->createToken('realtime-test')->plainTextToken;
    $this->withToken($memberToken)->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-live-stream.'.$stream->id,
    ])->assertSuccessful()->assertJsonStructure(['auth']);

    auth()->forgetGuards();
    $outsiderToken = $outsider->createToken('realtime-test')->plainTextToken;
    $this->withToken($outsiderToken)->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-live-stream.'.$stream->id,
    ])->assertForbidden();
});
