<?php

use App\Enums\LiveStreamStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('media.worker_id', 'worker-test');
    config()->set('media.mediamtx.api_url', 'http://mediamtx:9997');
    config()->set('media.recordings_root', '/recordings');

    Http::preventStrayRequests();
    Http::fake([
        'http://mediamtx:9997/*' => Http::response([], 200),
    ]);
});

test('media user can register an encrypted rtsp source in mediamtx', function () {
    $user = User::factory()->create(['role' => UserRole::MEDIA]);
    $church = Church::query()->create([
        'name' => 'Nossa Casa',
        'slug' => 'nossa-casa',
        'status' => 'active',
    ]);
    $church->assignMember($user);

    $response = $this->actingAs($user)->postJson('/api/live-streams', [
        'name' => 'Câmera do auditório',
        'source_url' => 'rtsp://camera-user:camera-password@camera.test/live',
        'source_on_demand' => false,
        'record' => true,
        'church_id' => $church->id,
    ]);

    $response->assertCreated()
        ->assertJsonMissing(['source_url' => 'rtsp://camera-user:camera-password@camera.test/live'])
        ->assertJsonPath('name', 'Câmera do auditório')
        ->assertJsonPath('status', LiveStreamStatus::READY->value);

    $liveStream = LiveStream::query()->firstOrFail();

    expect($liveStream->getRawOriginal('source_url'))
        ->not->toContain('camera-password')
        ->and($liveStream->source_url)->toBe('rtsp://camera-user:camera-password@camera.test/live');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://mediamtx:9997/v3/config/paths/add/'.$liveStream->path
        && $request['source'] === $liveStream->source_url
        && $request['record'] === true);
});

test('system user can drop an active mediamtx connection from logs', function () {
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);
    $liveStream = LiveStream::factory()->create([
        'status' => LiveStreamStatus::LIVE,
        'started_at' => now()->subMinute(),
    ]);

    $this->actingAs($system)
        ->post(route('admin.logsMetrics.liveStreams.stop', $liveStream))
        ->assertRedirect();

    expect($liveStream->refresh())
        ->status->toBe(LiveStreamStatus::STOPPED)
        ->ended_at->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'http://mediamtx:9997/v3/config/paths/delete/'.$liveStream->path);
});

test('member cannot register an rtsp source', function () {
    $member = User::factory()->create(['role' => UserRole::MEMBER]);

    $this->actingAs($member)->postJson('/api/live-streams', [
        'name' => 'Unauthorized camera',
        'source_url' => 'rtsp://camera.test/live',
    ])->assertForbidden();

    Http::assertNothingSent();
});

test('media user cannot create a transmission for another church', function () {
    $user = User::factory()->create(['role' => UserRole::MEDIA]);
    $userChurch = Church::query()->create([
        'name' => 'User Church',
        'slug' => 'user-church',
        'status' => 'active',
    ]);
    $otherChurch = Church::query()->create([
        'name' => 'Other Church',
        'slug' => 'other-church',
        'status' => 'active',
    ]);
    $userChurch->assignMember($user);

    $this->actingAs($user)->postJson('/api/live-streams', [
        'name' => 'Unauthorized target',
        'mode' => 'publisher',
        'church_id' => $otherChurch->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('church_id');

    expect(LiveStream::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('global administrators can create a transmission for any church', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $targetChurch = Church::query()->create([
        'name' => 'Selected Church',
        'slug' => 'selected-church',
        'status' => 'active',
    ]);

    $this->actingAs($user)->postJson('/api/live-streams', [
        'name' => 'Global transmission',
        'mode' => 'publisher',
        'church_id' => $targetChurch->id,
    ])->assertCreated();

    expect(LiveStream::query()->sole()->church_id)->toBe($targetChurch->id);
})->with([
    'superadmin' => UserRole::SUPERADMIN,
    'system' => UserRole::SYSTEM,
]);

test('a church can reserve two live streams but not a third one', function () {
    $user = User::factory()->create(['role' => UserRole::MEDIA]);
    $church = Church::query()->create([
        'name' => 'Single Stream Church',
        'slug' => 'single-stream-church',
        'status' => 'active',
    ]);
    $church->assignMember($user);

    $payload = [
        'name' => 'Main service',
        'source_url' => 'rtsp://camera.test/live',
    ];

    $this->actingAs($user)->postJson('/api/live-streams', $payload)->assertCreated();
    $this->actingAs($user)->postJson('/api/live-streams', $payload)
        ->assertCreated();
    $this->actingAs($user)->postJson('/api/live-streams', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('church');

    expect(LiveStream::query()->where('church_id', $church->id)->count())->toBe(2)
        ->and(LiveStream::query()->where('church_id', $church->id)->pluck('active_slot')->sort()->values()->all())
        ->toBe([1, 2]);
});

test('only media administrators and system users can open transmission control', function () {
    $this->withoutVite();
    $church = Church::query()->create([
        'name' => 'Control Church',
        'slug' => 'control-church',
        'status' => 'active',
    ]);
    $otherChurch = Church::query()->create([
        'name' => 'Other Control Church',
        'slug' => 'other-control-church',
        'status' => 'active',
    ]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $media = User::factory()->create(['role' => UserRole::MEDIA]);
    $system = User::factory()->create(['role' => UserRole::SYSTEM]);
    $church->assignMember($member);
    $church->assignMember($leader);
    $church->assignMember($media);

    $this->actingAs($member)->get('/dashboard/transmissoes')->assertForbidden();
    $this->actingAs($leader)->get('/dashboard/transmissoes')->assertForbidden();
    $this->actingAs($media)->get("/dashboard/transmissoes?church_id={$otherChurch->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/LiveStreams')
            ->where('church.id', $church->id)
            ->has('churches', 0));
    $this->actingAs($system)->get('/dashboard/transmissoes')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('church.id', $church->id)
            ->has('churches', 2));
});

test('global administrators can select any church even from another church domain', function (UserRole $role) {
    $this->withoutVite();
    $currentDomainChurch = Church::query()->create([
        'name' => 'Current Domain Church',
        'slug' => 'current-domain-church',
        'domain' => 'current-church.test',
        'status' => 'active',
    ]);
    $selectedChurch = Church::query()->create([
        'name' => 'Selected Church',
        'slug' => 'selected-church',
        'status' => 'active',
    ]);
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get("http://{$currentDomainChurch->domain}/dashboard/transmissoes?church_id={$selectedChurch->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('church.id', $selectedChurch->id)
            ->has('churches', 2));
})->with([
    'superadmin' => UserRole::SUPERADMIN,
    'system' => UserRole::SYSTEM,
]);

test('media user creates a protected publisher link and rotates its token', function () {
    $this->withoutVite();
    config()->set('media.mediamtx.public_rtmp_url', 'rtmp://stream.test:1935');
    $user = User::factory()->create(['role' => UserRole::MEDIA]);
    $church = Church::query()->create([
        'name' => 'Publisher Church',
        'slug' => 'publisher-church',
        'domain' => 'publisher.test',
        'status' => 'active',
    ]);
    $church->assignMember($user);

    $this->actingAs($user)->postJson('http://publisher.test/api/live-streams', [
        'name' => 'Sunday live',
        'mode' => 'publisher',
        'record' => true,
    ])->assertCreated()->assertJsonMissingPath('publish_token');

    $liveStream = LiveStream::query()->firstOrFail();
    $firstToken = $liveStream->publish_token;

    expect($liveStream)
        ->input_mode->toBe('publisher')
        ->and($firstToken)->toBeString()->not->toBeEmpty();

    $this->get('http://publisher.test/dashboard/transmissoes')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('streams.0.token', $firstToken)
            ->where('streams.0.ingest_server', 'rtmp://publisher.test:1935')
            ->where('streams.0.stream_key', "{$liveStream->path}?token={$firstToken}")
            ->where('streams.0.ingest_url', "rtmp://publisher.test:1935/{$liveStream->path}?token={$firstToken}")
            ->where('streams.0.public_url', "https://publisher.test/live-streams/{$liveStream->id}"));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'http://mediamtx:9997/v3/config/paths/add/'.$liveStream->path
        && $request['source'] === 'publisher');

    $this->postJson('/api/internal/media/auth', [
        'action' => 'publish',
        'path' => $liveStream->path,
        'token' => 'invalid-token',
    ])->assertUnauthorized();
    $this->postJson('/api/internal/media/auth', [
        'action' => 'publish',
        'path' => $liveStream->path,
        'token' => $firstToken,
    ])->assertNoContent();
    $this->withHeader('Host', 'webserver')->postJson('/api/internal/media/auth', [
        'action' => 'publish',
        'path' => $liveStream->path,
        'token' => $firstToken,
    ])->assertNoContent();
    $this->withoutHeader('Host');
    $this->postJson('/api/internal/media/auth', [
        'action' => 'publish',
        'path' => $liveStream->path,
        'query' => 'token='.rawurlencode($firstToken),
    ])->assertNoContent();

    $response = $this->actingAs($user)
        ->postJson("/api/live-streams/{$liveStream->id}/rotate-token")
        ->assertSuccessful();
    $secondToken = $response->json('token');

    expect($secondToken)->toBeString()->not->toBe($firstToken)
        ->and($liveStream->refresh()->publish_token)->toBe($secondToken);

    $this->postJson('/api/internal/media/auth', [
        'action' => 'publish',
        'path' => $liveStream->path,
        'token' => $firstToken,
    ])->assertUnauthorized();
    $this->postJson('/api/internal/media/auth', [
        'action' => 'publish',
        'path' => $liveStream->path,
        'token' => $secondToken,
    ])->assertNoContent();
});

test('media user can create a private transmission', function () {
    $this->withoutVite();
    $user = User::factory()->create(['role' => UserRole::MEDIA]);
    $church = Church::query()->create([
        'name' => 'Private Stream Church',
        'slug' => 'private-stream-church',
        'status' => 'active',
    ]);
    $church->assignMember($user);

    $this->actingAs($user)->postJson('/api/live-streams', [
        'name' => 'Members service',
        'mode' => 'publisher',
        'record' => true,
        'is_public' => false,
    ])->assertCreated();

    $liveStream = LiveStream::query()->firstOrFail();

    expect($liveStream->is_public)->toBeFalse();

    $this->get('/dashboard/transmissoes')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('streams.0.is_public', false));
});

test('only superadmin and system users can delete transmissions', function (UserRole $role, int $status) {
    $user = User::factory()->create(['role' => $role]);
    $church = Church::query()->create([
        'name' => 'Deletion Church',
        'slug' => 'deletion-church-'.$role->value,
        'status' => 'active',
    ]);
    $church->assignMember($user);
    $liveStream = LiveStream::factory()->create([
        'church_id' => $church->id,
        'status' => LiveStreamStatus::STOPPED,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/live-streams/{$liveStream->id}")
        ->assertStatus($status);

    expect(LiveStream::query()->whereKey($liveStream->id)->exists())
        ->toBe($status !== 204);
})->with([
    'media' => [UserRole::MEDIA, 403],
    'church leader' => [UserRole::CHURCH_LEADER, 403],
    'superadmin' => [UserRole::SUPERADMIN, 204],
    'system' => [UserRole::SYSTEM, 204],
]);
