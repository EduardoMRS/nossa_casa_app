<?php

use App\Models\Church;
use App\Models\Community;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('portal highlights communities nearest to the visitor coordinates', function () {
    $nearCommunity = Community::factory()->create(['name' => 'Near Community']);
    $farCommunity = Community::factory()->create(['name' => 'Far Community']);
    $nearChurch = Church::factory()->create([
        'name' => 'Near Church',
        'domain' => 'near.test',
        'community_id' => $nearCommunity->id,
    ]);
    $farChurch = Church::factory()->create([
        'name' => 'Far Church',
        'domain' => 'far.test',
        'community_id' => $farCommunity->id,
    ]);
    $nearChurch->address()->create([
        'country' => 'Brasil',
        'state' => 'AM',
        'city' => 'Manaus',
        'street' => 'Centro',
        'zipcode' => '69000-000',
        'latitude' => -3.1190,
        'longitude' => -60.0217,
    ]);
    $farChurch->address()->create([
        'country' => 'Brasil',
        'state' => 'SP',
        'city' => 'Sao Paulo',
        'street' => 'Centro',
        'zipcode' => '01000-000',
        'latitude' => -23.5505,
        'longitude' => -46.6333,
    ]);

    $this->get('http://platform.test/?latitude=-3.1189&longitude=-60.0215')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Index')
            ->where('locationApplied', true)
            ->where('nearbyCommunities.0.id', $nearCommunity->id)
            ->where('nearbyCommunities.0.churches.0.id', $nearChurch->id));
});

test('manifest and service worker are generated dynamically for the church domain', function () {
    $community = Community::factory()->create();
    $church = Church::factory()->create([
        'name' => 'PWA Church',
        'domain' => 'pwa.test',
        'community_id' => $community->id,
    ]);
    Setting::query()->where('church_id', $church->id)->firstOrFail()->update([
        'options' => [
            'branding' => [
                'brand_name' => 'PWA Church App',
                'primary_color' => '#123456',
                'surface_color' => '#f0f0f0',
            ],
        ],
    ]);

    $this->get('http://pwa.test/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'PWA Church App')
        ->assertJsonPath('theme_color', '#123456')
        ->assertJsonPath('shortcuts.0.url', '/biblioteca/biblia');

    $this->get('http://pwa.test/sw.js')
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertSee('nossa-casa', escape: false)
        ->assertSee('CACHE_BIBLES', escape: false)
        ->assertSee('BIBLE_CACHE_READY', escape: false)
        ->assertSee('cachedMarker', escape: false)
        ->assertSee('CACHE_SCOPE', escape: false)
        ->assertSee("request.headers.get('X-Inertia')", escape: false)
        ->assertSee("cache.match('/', { ignoreVary: true })", escape: false)
        ->assertSee("url.pathname.startsWith('/api/bible/')", escape: false);

    $this->artisan('service-worker:update')->assertSuccessful();
});

test('authenticated users can persist a browser push subscription', function () {
    $user = User::factory()->create();
    $endpoint = 'https://push.example.test/subscriptions/abc';

    $this->actingAs($user)->postJson('http://platform.test/api/push-subscriptions', [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => str_repeat('a', 65),
            'auth' => str_repeat('b', 24),
        ],
        'content_encoding' => 'aes128gcm',
    ])->assertCreated();

    $subscription = PushSubscription::query()->firstOrFail();
    expect($subscription->user_id)->toBe($user->id)
        ->and($subscription->endpoint_hash)->toBe(hash('sha256', $endpoint));
});

test('application shell prevents zoom on mobile viewports and form controls', function () {
    $this->get('http://platform.test/')
        ->assertOk()
        ->assertSee(
            '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">',
            escape: false,
        );

    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('@media (max-width: 767px)')
        ->toContain("input:not([type='checkbox'])")
        ->toContain('font-size: 16px;');
});
