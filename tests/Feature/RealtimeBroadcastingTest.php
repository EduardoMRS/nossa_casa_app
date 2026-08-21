<?php

use App\Enums\LiveStreamStatus;
use App\Events\LiveStreamCommentsUpdated;
use App\Events\LiveStreamUpdated;
use App\Events\SystemMetricsUpdated;
use App\Models\Comment;
use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

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

test('live chat broadcasts the canonical comment snapshot', function () {
    $stream = LiveStream::factory()->create(['is_public' => true]);
    $user = User::factory()->create();
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
