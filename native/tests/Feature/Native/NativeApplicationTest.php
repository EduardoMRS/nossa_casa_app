<?php

namespace Tests\Feature\Native;

use App\Models\ContentCache;
use App\Models\ServerProfile;
use App\NativeComponents\Portal;
use App\Services\ChurchSelectionService;
use App\Services\NativeApiClient;
use App\Services\NativePushRegistrationService;
use App\Services\OfflineContentService;
use App\Services\OfflineOperationPolicy;
use App\Services\ServerDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Facades\PushNotifications;
use Native\Mobile\Facades\SecureStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class NativeApplicationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nativeRoutes')]
    public function test_native_routes_render_the_edge_application_screens(string $route): void
    {
        $this->get($route)->assertOk();
    }

    public static function nativeRoutes(): array
    {
        return [['/'], ['/server'], ['/login'], ['/portal'], ['/church-selector'], ['/offline']];
    }

    public function test_discovery_accepts_a_valid_https_nossa_casa_instance(): void
    {
        $instanceId = (string) Str::ulid();
        Http::fake(['https://church.example.org/.well-known/nossa-casa.json' => Http::response([
            'protocol' => 'nossa-casa',
            'protocol_version' => 1,
            'instance_id' => $instanceId,
            'instance_name' => 'Igreja Teste',
            'api_base_url' => 'https://church.example.org/api',
            'web_base_url' => 'https://church.example.org',
            'api_version' => 1,
            'capabilities' => ['mobile_auth'],
        ])]);

        $profile = $this->app->make(ServerDiscoveryService::class)->discover('CHURCH.example.org');

        $this->assertSame($instanceId, $profile->instance_id);
        $this->assertSame('https://church.example.org', $profile->origin);
        $this->assertTrue($profile->selected);
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidServers')]
    public function test_discovery_rejects_invalid_servers(string $server, array $response = []): void
    {
        Http::fake(['*' => Http::response($response)]);
        $this->expectException(ValidationException::class);
        $this->app->make(ServerDiscoveryService::class)->discover($server);
    }

    public static function invalidServers(): array
    {
        return [
            'plain HTTP' => ['http://church.example.org'],
            'username' => ['https://user@church.example.org'],
            'password' => ['https://user:secret@church.example.org'],
            'foreign API origin' => ['https://church.example.org', [
                'protocol' => 'nossa-casa',
                'protocol_version' => 1,
                'instance_id' => '01K3G82R53Z3CHT0MNC1H5T1Z2',
                'api_base_url' => 'https://evil.example/api',
                'web_base_url' => 'https://church.example.org',
            ]],
        ];
    }

    public function test_api_client_scopes_bearer_and_church_headers_to_the_discovered_origin(): void
    {
        $server = $this->serverProfile(['selected_church_id' => (string) Str::ulid()]);
        $session = [
            'access_token' => 'secret-access-token',
            'access_token_expires_at' => now()->addHour()->toIso8601String(),
        ];
        SecureStorage::shouldReceive('get')->once()
            ->with("servers/{$server->instance_id}/session")
            ->andReturn(json_encode($session, JSON_THROW_ON_ERROR));
        Http::fake(['https://church.example.org/api/portal' => Http::response([
            'data' => ['latestPosts' => []],
        ])]);

        $payload = $this->app->make(NativeApiClient::class)->get($server, 'portal');

        $this->assertSame(['latestPosts' => []], $payload);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-access-token')
            && $request->hasHeader('X-Church-ID', $server->selected_church_id));

        $this->expectException(RuntimeException::class);
        $this->app->make(NativeApiClient::class)->get($server, 'https://evil.example/api/portal');
    }

    public function test_church_selection_is_automatic_only_when_it_is_unambiguous(): void
    {
        $service = $this->app->make(ChurchSelectionService::class);
        $churchA = (string) Str::ulid();
        $churchB = (string) Str::ulid();
        $single = $this->serverProfile(['instance_id' => (string) Str::ulid()]);

        $this->assertSame($churchA, $service->choose($single, ['memberships' => [['church_id' => $churchA]]]));

        $multiple = $this->serverProfile(['instance_id' => (string) Str::ulid()]);
        $this->assertNull($service->choose($multiple, ['memberships' => [
            ['church_id' => $churchA],
            ['church_id' => $churchB],
        ]]));
    }

    public function test_offline_cache_payload_is_encrypted_at_rest(): void
    {
        $server = $this->serverProfile();
        $cache = ContentCache::query()->create([
            'server_profile_id' => $server->id,
            'cache_key' => 'portal',
            'payload' => ['private_marker' => 'sensitive-local-content'],
            'expires_at' => now()->addHour(),
            'refreshed_at' => now(),
        ]);
        $stored = ContentCache::query()->toBase()->where('id', $cache->id)->value('payload');

        $this->assertStringNotContainsString('sensitive-local-content', $stored);
        $this->assertSame(['private_marker' => 'sensitive-local-content'], $cache->fresh()->payload);
    }

    public function test_public_content_and_bible_can_be_synchronized_with_expiry_policies(): void
    {
        $server = $this->serverProfile();
        SecureStorage::shouldReceive('get')->times(5)->andReturn(null);
        Http::fake([
            'https://church.example.org/api/portal' => Http::response(['latestPosts' => []]),
            'https://church.example.org/api/content/posts' => Http::response(['items' => [['id' => 'post']]]),
            'https://church.example.org/api/content/events' => Http::response(['items' => [['id' => 'event']]]),
            'https://church.example.org/api/content/library' => Http::response(['items' => []]),
            'https://church.example.org/api/bible/NVI/offline' => Http::response(['books' => [['name' => 'Genesis']]]),
        ]);
        $offline = $this->app->make(OfflineContentService::class);

        $results = $offline->syncPublic($server);
        $bible = $offline->downloadBible($server, 'NVI');

        $this->assertSame([
            'portal' => true,
            'posts' => true,
            'events' => true,
            'library' => true,
        ], $results);
        $this->assertCount(5, $offline->summary($server));
        $this->assertSame('bible:nvi', $bible->cache_key);
        $this->assertTrue($bible->expires_at->greaterThan(now()->addDays(29)));
    }

    public function test_portal_refetches_canonical_state_when_the_app_resumes(): void
    {
        $this->serverProfile();
        SecureStorage::shouldReceive('get')->times(4)->andReturn(null);
        Http::fakeSequence('https://church.example.org/api/portal')
            ->push(['data' => ['latestPosts' => [['id' => 'before', 'title' => 'Before']]]])
            ->push(['data' => ['latestPosts' => [['id' => 'after', 'title' => 'After']]]]);

        $portal = $this->app->make(Portal::class);
        $portal->mount();
        $this->assertSame('before', $portal->content['latestPosts'][0]['id']);

        $portal->onResume();
        $this->assertSame('after', $portal->content['latestPosts'][0]['id']);

        Http::assertSentCount(2);
    }

    public function test_native_push_token_is_registered_and_removed_for_the_mobile_device(): void
    {
        $server = $this->serverProfile();
        $deviceId = (string) Str::ulid();
        $session = json_encode([
            'device_id' => $deviceId,
            'access_token' => 'secret-access-token',
            'access_token_expires_at' => now()->addHour()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);
        SecureStorage::shouldReceive('get')->times(5)
            ->with("servers/{$server->instance_id}/session")
            ->andReturn($session);
        PushNotifications::shouldReceive('getToken')->once()->andReturn('fcm-device-token');
        Http::fake([
            'https://church.example.org/api/push/devices' => Http::response(['data' => ['id' => (string) Str::ulid()]], 201),
            'https://church.example.org/api/push/devices/*' => Http::response(status: 204),
        ]);
        $push = $this->app->make(NativePushRegistrationService::class);

        $push->synchronize($server);
        $push->unregister($server);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://church.example.org/api/push/devices'
            && $request['device_id'] === $deviceId
            && $request['transport'] === 'fcm'
            && $request['token'] === 'fcm-device-token');
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://church.example.org/api/push/devices/'.$deviceId);
        Http::assertSentCount(2);
    }

    public function test_offline_operations_are_not_queued_and_have_stable_idempotency_keys(): void
    {
        $policy = $this->app->make(OfflineOperationPolicy::class);
        $instanceId = (string) Str::ulid();
        $first = $policy->idempotencyKey($instanceId, 'reaction', ['type' => 'heart', 'id' => 'post']);
        $second = $policy->idempotencyKey($instanceId, 'reaction', ['id' => 'post', 'type' => 'heart']);

        $this->assertSame($first, $second);
        $this->assertFalse($policy->canQueue('reaction'));

        $this->expectException(ValidationException::class);
        $policy->ensureOnline(false, 'reaction');
    }

    /** @param array<string, mixed> $overrides */
    private function serverProfile(array $overrides = []): ServerProfile
    {
        return ServerProfile::query()->create([
            'instance_id' => (string) Str::ulid(),
            'name' => 'Nossa Casa Test',
            'origin' => 'https://church.example.org',
            'api_base_url' => 'https://church.example.org/api',
            'web_base_url' => 'https://church.example.org',
            'api_version' => 1,
            'capabilities' => ['mobile_auth', 'church_context'],
            'selected' => true,
            ...$overrides,
        ]);
    }
}
