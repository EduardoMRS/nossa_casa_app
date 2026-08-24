<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('api routes use the api middleware stack without a duplicated prefix', function () {
    $matchingRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => $route->uri() === 'api/prayer-requests' && in_array('GET', $route->methods(), true));

    expect($matchingRoutes)->toHaveCount(1);

    $route = Route::getRoutes()->match(Request::create('/api/prayer-requests', 'GET'));
    $middleware = $route->gatherMiddleware();

    expect($middleware)
        ->toContain('api', 'auth:sanctum')
        ->not->toContain('web');
});

test('api routes authenticate first party web sessions through sanctum', function () {
    $church = Church::factory()->create(['domain' => 'sanctum-church.test']);
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($user);

    $this->post('http://sanctum-church.test/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->withHeader('Origin', 'http://sanctum-church.test')
        ->getJson('http://sanctum-church.test/api/prayer-requests')
        ->assertSuccessful();
});

test('api routes authenticate native bearer tokens through sanctum', function () {
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    $token = $user->createToken('native-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/prayer-requests')
        ->assertSuccessful();
});

test('native bearer mutations do not require csrf and retain church authorization', function () {
    $church = Church::factory()->create();
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($user);
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $user->id,
        'title' => 'Native API post',
        'slug' => 'native-api-post',
        'content' => 'Native API content.',
        'published_at' => now(),
    ]);

    $this->withToken($user->createToken('native-mutation')->plainTextToken)
        ->postJson('/api/reactions', [
            'reactionable_type' => 'post',
            'reactionable_id' => $post->id,
            'content' => 'like',
        ])
        ->assertCreated();
});

test('a native bearer token cannot mutate another church resource', function () {
    $homeChurch = Church::factory()->create();
    $otherChurch = Church::factory()->create();
    $user = User::factory()->create(['role' => UserRole::MEMBER]);
    $otherAuthor = User::factory()->create(['role' => UserRole::MEMBER]);
    $homeChurch->assignMember($user);
    $otherChurch->assignMember($otherAuthor);
    $post = Post::query()->create([
        'church_id' => $otherChurch->id,
        'author_id' => $otherAuthor->id,
        'title' => 'Other church post',
        'slug' => 'other-church-post',
        'content' => 'Other church content.',
        'published_at' => now(),
    ]);

    $this->withToken($user->createToken('native-cross-tenant')->plainTextToken)
        ->postJson('/api/reactions', [
            'reactionable_type' => 'post',
            'reactionable_id' => $post->id,
            'content' => 'like',
        ])
        ->assertForbidden();
});

test('api routes continue applying the requested locale', function () {
    config()->set('app.locales', ['en', 'pt']);

    $this->withHeader('X-Locale', 'pt-BR')
        ->postJson('/api/prayer-requests', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');

    expect(app()->getLocale())->toBe('pt');
});
