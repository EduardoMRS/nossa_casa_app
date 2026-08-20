<?php

use App\Enums\LiveStreamStatus;
use App\Models\LiveStream;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('media.mediamtx.api_url', 'http://mediamtx:9997');
    config()->set('media.recordings_root', '/recordings');

    Http::preventStrayRequests();
});

test('active live stream paths are restored after mediamtx restarts', function () {
    $missingPath = LiveStream::factory()->create([
        'status' => LiveStreamStatus::READY,
        'input_mode' => 'publisher',
        'source_url' => 'publisher',
    ]);
    $existingPath = LiveStream::factory()->create([
        'status' => LiveStreamStatus::OFFLINE,
        'input_mode' => 'pull',
        'source_url' => 'rtsp://camera.test/live',
    ]);
    $stoppedPath = LiveStream::factory()->create([
        'status' => LiveStreamStatus::STOPPED,
    ]);

    Http::fake([
        'http://mediamtx:9997/v3/config/paths/list' => Http::response([
            'items' => [
                ['name' => 'all_others'],
                ['name' => $existingPath->path],
            ],
        ]),
        'http://mediamtx:9997/*' => Http::response([], 200),
    ]);

    $this->artisan('media:reconcile-paths')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://mediamtx:9997/v3/config/paths/add/'.$missingPath->path
        && $request['source'] === 'publisher');
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), $existingPath->path));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), $stoppedPath->path));
    Http::assertSentCount(2);
});
