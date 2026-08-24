<?php

use App\Models\AppInstance;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('app.url', 'https://nossa-casa.test');
    config()->set('native.base_url', 'https://nossa-casa.test');
    config()->set('native.instance_id', null);
    config()->set('native.instance_name', 'Nossa Casa Test');
});

test('discovery publishes a stable installation identity and capabilities', function () {
    $first = $this->getJson('https://nossa-casa.test/.well-known/nossa-casa.json')
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertJsonPath('protocol', 'nossa-casa')
        ->assertJsonPath('protocol_version', 1)
        ->assertJsonPath('instance_name', 'Nossa Casa Test')
        ->assertJsonPath('api_base_url', 'https://nossa-casa.test/api')
        ->assertJsonPath('web_base_url', 'https://nossa-casa.test')
        ->assertJsonPath('auth_driver', 'sanctum')
        ->assertJsonPath('api_version', 1)
        ->assertJsonPath('realtime.auth_path', '/api/broadcasting/auth')
        ->assertJsonStructure([
            'instance_id',
            'capabilities',
            'minimum_app_version',
            'privacy_url',
            'terms_url',
            'realtime' => ['enabled', 'key', 'host', 'port', 'scheme', 'auth_path'],
        ]);

    $second = $this->getJson('https://nossa-casa.test/.well-known/nossa-casa.json')
        ->assertSuccessful();

    expect(Str::isUlid($first->json('instance_id')))->toBeTrue()
        ->and($second->json('instance_id'))->toBe($first->json('instance_id'))
        ->and(AppInstance::query()->count())->toBe(1);
});

test('a configured installation ulid overrides database generation', function () {
    $instanceId = (string) Str::ulid();
    config()->set('native.instance_id', $instanceId);

    $this->getJson('https://nossa-casa.test/.well-known/nossa-casa.json')
        ->assertSuccessful()
        ->assertJsonPath('instance_id', $instanceId);

    expect(AppInstance::query()->count())->toBe(0);
});

test('production rejects insecure discovery and api requests', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->getJson('http://nossa-casa.test/.well-known/nossa-casa.json')
        ->assertStatus(426)
        ->assertJsonPath('code', 'HTTPS_REQUIRED');

    $this->postJson('http://nossa-casa.test/api/prayer-requests', [])
        ->assertStatus(426)
        ->assertJsonPath('code', 'HTTPS_REQUIRED');

    $this->getJson('https://nossa-casa.test/.well-known/nossa-casa.json')
        ->assertSuccessful();
});
