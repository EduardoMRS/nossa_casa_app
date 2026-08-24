<?php

use App\Contracts\NativePushProvider;
use App\Data\PushMessage;
use App\Enums\NotificationCategory;
use App\Enums\PushTransport;
use App\Jobs\SendNativePush;
use App\Models\AppInstance;
use App\Models\DevicePushToken;
use App\Models\User;
use App\Services\NativePushService;
use App\Services\Push\ApnsPushProvider;
use App\Services\Push\FcmPushProvider;
use App\Services\Push\NullPushProvider;
use App\Services\Push\UnifiedPushProvider;
use App\Services\Push\WebPushProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('authenticated user can register update and remove an encrypted device token', function () {
    $user = User::factory()->create();
    $deviceId = (string) Str::ulid();
    Sanctum::actingAs($user);

    $this->postJson('/api/push/devices', [
        'device_id' => $deviceId,
        'transport' => 'fcm',
        'token' => 'private-device-token',
        'app_version' => '0.1.0',
        'locale' => 'pt_BR',
        'enabled_categories' => ['event', 'live_stream'],
    ])->assertCreated()->assertJsonStructure(['data' => ['id']]);

    $device = DevicePushToken::query()->firstOrFail();
    $storedToken = DB::table('device_push_tokens')->where('id', $device->id)->value('token');

    expect($device->token)->toBe('private-device-token')
        ->and($storedToken)->not->toContain('private-device-token')
        ->and($device->accepts(NotificationCategory::EVENT))->toBeTrue()
        ->and($device->accepts(NotificationCategory::KIDS))->toBeFalse();

    $this->postJson('/api/push/devices', [
        'device_id' => $deviceId,
        'transport' => 'fcm',
        'token' => 'rotated-device-token',
    ])->assertOk();

    expect(DevicePushToken::query()->count())->toBe(1)
        ->and($device->fresh()->token)->toBe('rotated-device-token');

    $this->deleteJson('/api/push/devices/'.$deviceId)->assertNoContent();
    expect(DevicePushToken::query()->count())->toBe(0);
});

test('all transports implement the provider contract', function () {
    expect([
        new ApnsPushProvider,
        new FcmPushProvider,
        new UnifiedPushProvider,
        new WebPushProvider,
        new NullPushProvider,
    ])->each->toBeInstanceOf(NativePushProvider::class);
});

test('push disabled is a successful no-op without an external request', function () {
    config(['services.native_push.default' => 'null']);
    Http::fake();
    $device = DevicePushToken::factory()->create();
    $message = new PushMessage('Title', 'Body', NotificationCategory::CONTENT, '/posts/example');

    $result = app(NativePushService::class)->send($device, $message);

    expect($result->sent)->toBeFalse()->and($result->error)->toBe('PUSH_DISABLED');
    Http::assertNothingSent();
});

test('invalid FCM tokens are invalidated without retrying', function () {
    config([
        'services.native_push.default' => 'fcm',
        'services.native_push.fcm.project_id' => 'project-test',
        'services.native_push.fcm.access_token' => 'server-only-access-token',
    ]);
    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'error' => ['status' => 'NOT_FOUND'],
        ], 404),
    ]);
    $device = DevicePushToken::factory()->create(['transport' => PushTransport::FCM]);
    $message = new PushMessage('Title', 'Body', NotificationCategory::EVENT, '/events/example');

    (new SendNativePush($device->id, $message))->handle(app(NativePushService::class));

    expect($device->fresh()->invalidated_at)->not->toBeNull();
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer server-only-access-token')
        && $request['message']['data']['category'] === 'event'
        && $request['message']['data']['deep_link'] === '/events/example');
});

test('APNs and UnifiedPush send only through their configured server transports', function () {
    config([
        'services.native_push.apns.bundle_id' => 'br.org.nossacasa.app',
        'services.native_push.apns.bearer_token' => 'server-only-apns-token',
        'services.native_push.apns.endpoint' => 'https://api.push.apple.com',
        'services.native_push.unified.enabled' => true,
        'services.native_push.unified.allowed_hosts' => ['push.example.test'],
    ]);
    Http::fake([
        'https://api.push.apple.com/*' => Http::response('', 200, ['apns-id' => 'apns-message-id']),
        'https://push.example.test/*' => Http::response([], 202),
    ]);
    $message = new PushMessage('Title', 'Body', NotificationCategory::SYSTEM, '/portal');
    $apnsDevice = DevicePushToken::factory()->create([
        'transport' => PushTransport::APNS,
        'token' => 'ios-device-token',
    ]);
    $unifiedDevice = DevicePushToken::factory()->create([
        'transport' => PushTransport::UNIFIED,
        'token' => 'https://push.example.test/device/endpoint',
    ]);

    $apnsResult = (new ApnsPushProvider)->send($message, $apnsDevice);
    $unifiedResult = (new UnifiedPushProvider)->send($message, $unifiedDevice);

    expect($apnsResult->sent)->toBeTrue()
        ->and($apnsResult->providerMessageId)->toBe('apns-message-id')
        ->and($unifiedResult->sent)->toBeTrue();
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.push.apple.com/3/device/ios-device-token'
        && $request->hasHeader('Authorization', 'Bearer server-only-apns-token')
        && $request->hasHeader('apns-topic', 'br.org.nossacasa.app'));
});

test('web push keeps VAPID server-side and rejects an unknown subscription', function () {
    config([
        'services.webpush.public_key' => 'public-vapid-key',
        'services.webpush.private_key' => 'server-only-private-vapid-key',
        'services.webpush.subject' => 'mailto:support@example.test',
    ]);
    $provider = new WebPushProvider;
    $device = DevicePushToken::factory()->create([
        'transport' => PushTransport::WEB,
        'token' => 'https://push.example.test/subscription',
    ]);

    $result = $provider->send(
        new PushMessage('Title', 'Body', NotificationCategory::CONTENT, '/posts/example'),
        $device,
    );

    expect($provider->supports(PushTransport::WEB))->toBeTrue()
        ->and($result->sent)->toBeFalse()
        ->and($result->invalidToken)->toBeTrue()
        ->and($result->error)->toBe('WEB_SUBSCRIPTION_NOT_FOUND');
});

test('push gateway requires a fresh non replayable instance signature and queues only owned devices', function () {
    config([
        'services.native_push.gateway.enabled' => true,
    ]);
    Cache::clear();
    Bus::fake([SendNativePush::class]);
    $keyPair = sodium_crypto_sign_keypair();
    $instance = AppInstance::query()->create([
        'key' => 'gateway-client',
        'push_gateway_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        'push_gateway_enabled' => true,
    ]);
    $foreignInstance = AppInstance::query()->create(['key' => 'foreign-gateway-client']);
    $device = DevicePushToken::factory()->create(['app_instance_id' => $instance->id]);
    $foreignDevice = DevicePushToken::factory()->create(['app_instance_id' => $foreignInstance->id]);
    $payload = [
        'device_ids' => [$device->id, $foreignDevice->id],
        'title' => 'Event reminder',
        'body' => 'The event starts soon.',
        'category' => 'event',
        'deep_link' => '/events/example',
        'data' => ['event_id' => 'example'],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $signaturePayload = $instance->id.'.'.$timestamp.'.'.$nonce.'.'.hash('sha256', $body);
    $signature = base64_encode(sodium_crypto_sign_detached(
        $signaturePayload,
        sodium_crypto_sign_secretkey($keyPair),
    ));
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_PUSH_INSTANCE_ID' => $instance->id,
        'HTTP_X_PUSH_TIMESTAMP' => $timestamp,
        'HTTP_X_PUSH_NONCE' => $nonce,
        'HTTP_X_PUSH_SIGNATURE' => $signature,
    ];

    $this->call('POST', '/api/push/gateway', server: $server, content: $body)
        ->assertAccepted()
        ->assertJsonPath('data.accepted', 1);
    Bus::assertDispatched(SendNativePush::class, 1);

    $this->call('POST', '/api/push/gateway', server: $server, content: $body)
        ->assertConflict()
        ->assertJsonPath('code', 'PUSH_GATEWAY_REPLAYED');
});
