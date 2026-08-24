<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;

test('public content resources expose stable structures without model internals', function () {
    $church = Church::factory()->create(['domain' => 'contract.test']);
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($author);
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Contract post',
        'slug' => 'contract-post',
        'content' => 'Canonical **content**.',
        'published_at' => now()->subMinute(),
    ]);
    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Contract event',
        'slug' => 'contract-event',
        'description' => 'Canonical event.',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
    ]);
    $headers = ['X-Church-ID' => $church->id];

    $this->getJson(route('api.content.posts'), $headers)
        ->assertOk()
        ->assertJsonPath('posts.data.0.id', $post->id)
        ->assertJsonStructure([
            'posts' => ['data' => [['id', 'title', 'slug', 'excerpt', 'published_at', 'church', 'author', 'categories', 'cover_url', 'comments_count', 'reactions_count', 'views_count']]],
            'categories',
            'filters' => ['search', 'category', 'date_from', 'date_to', 'sort'],
            'mostViewed',
        ])
        ->assertJsonMissingPath('posts.data.0.translations')
        ->assertJsonMissingPath('posts.data.0.author.email');

    $this->getJson(route('api.content.events'), $headers)
        ->assertOk()
        ->assertJsonPath('events.data.0.id', $event->id)
        ->assertJsonStructure([
            'events' => ['data' => [['id', 'title', 'slug', 'description', 'cover_path', 'start_time', 'end_time', 'church']]],
        ]);

    $this->getJson(route('api.content.gallery'), $headers)
        ->assertOk()
        ->assertJsonStructure(['media' => ['data'], 'categories', 'canInteract', 'view']);

    $this->getJson(route('api.content.library'), $headers)
        ->assertOk()
        ->assertJsonStructure(['items' => ['data'], 'bible']);

    $this->getJson(route('api.portal'), $headers)
        ->assertOk()
        ->assertJsonStructure([
            'stats' => ['events', 'gallery', 'posts'],
            'featuredEvents',
            'latestPosts',
            'latestRecordings',
            'calendarEvents',
            'dailyVerse',
        ]);
});

test('web and api use the same canonical post dto', function () {
    $this->withoutVite();
    $church = Church::factory()->create(['domain' => 'same-contract.test']);
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($author);
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Shared DTO',
        'slug' => 'shared-dto',
        'content' => 'One serializer for both clients.',
        'published_at' => now()->subMinute(),
    ]);

    $web = $this->get('http://same-contract.test/posts')->assertOk();
    $api = $this->getJson(route('api.content.posts'), ['X-Church-ID' => $church->id])->assertOk();

    expect($web->viewData('page')['props']['posts']['data'][0])
        ->toEqual($api->json('posts.data.0'))
        ->and($api->json('posts.data.0.id'))->toBe($post->id);
});
