<?php

use App\Models\PublicAccessLog;
use App\Models\User;
use App\Services\PublicAccessLogger;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('services.ip_geolocation.enabled', false);

    Route::middleware('web')->group(function (): void {
        Route::get('/testing/public-page', fn () => response('<html><body>Public</body></html>'))
            ->name('testing.public-page');

        Route::post('/testing/public-form', fn () => response('<html><body>Saved</body></html>'))
            ->name('testing.public-form');

        Route::middleware('auth')->get('/testing/private-page', fn () => response('<html><body>Private</body></html>'))
            ->name('testing.private-page');
    });
});

test('records an anonymous access when a guest views a public page', function (): void {
    $this->get('/testing/public-page')->assertOk();

    expect(PublicAccessLog::query()->count())->toBe(1);

    $log = PublicAccessLog::query()->firstOrFail();

    expect($log->path)->toBe('/testing/public-page')
        ->and($log->route_name)->toBe('testing.public-page')
        ->and($log->visited_at)->not->toBeNull();
});

test('never stores a user identifier on the access log', function (): void {
    $this->get('/testing/public-page')->assertOk();

    $log = PublicAccessLog::query()->firstOrFail();

    expect($log->getAttributes())->not->toHaveKey('user_id')
        ->and(array_keys($log->getAttributes()))->not->toContain('user_id');
});

test('hashes the visitor ip instead of storing it in plain text', function (): void {
    $ip = '8.8.8.8';

    $this->call('GET', '/testing/public-page', server: ['REMOTE_ADDR' => $ip])->assertOk();

    $log = PublicAccessLog::query()->firstOrFail();

    expect($log->ip_hash)->not->toBeNull()
        ->and($log->ip_hash)->not->toBe($ip)
        ->and($log->ip_hash)->toBe(hash_hmac('sha256', $ip, (string) config('app.key')));
});

test('does not record access for authenticated requests', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/testing/private-page');

    expect(PublicAccessLog::query()->count())->toBe(0);
});

test('does not record access for non-GET requests', function (): void {
    $this->post('/testing/public-form');

    expect(PublicAccessLog::query()->count())->toBe(0);
});

test('does not record access for JSON/XHR requests', function (): void {
    $this->getJson('/testing/public-page');

    expect(PublicAccessLog::query()->count())->toBe(0);
});

test('aggregates accesses into a visitor map grouped by region', function (): void {
    PublicAccessLog::query()->create([
        'ip_hash' => str_repeat('a', 64),
        'country_code' => 'BR',
        'country' => 'Brazil',
        'region' => 'São Paulo',
        'latitude' => -23.55,
        'longitude' => -46.63,
        'path' => '/pt',
        'visited_at' => now(),
    ]);
    PublicAccessLog::query()->create([
        'ip_hash' => str_repeat('b', 64),
        'country_code' => 'BR',
        'country' => 'Brazil',
        'region' => 'São Paulo',
        'latitude' => -23.55,
        'longitude' => -46.63,
        'path' => '/pt/posts',
        'visited_at' => now(),
    ]);
    PublicAccessLog::query()->create([
        'ip_hash' => str_repeat('c', 64),
        'country_code' => 'US',
        'country' => 'United States',
        'region' => 'California',
        'latitude' => 36.77,
        'longitude' => -119.41,
        'path' => '/en',
        'visited_at' => now(),
    ]);

    $map = app(PublicAccessLogger::class)->visitorMap();

    expect($map)->toHaveCount(2)
        ->and($map->first()['country_code'])->toBe('BR')
        ->and($map->first()['visits'])->toBe(2)
        ->and($map->last()['visits'])->toBe(1);
});
