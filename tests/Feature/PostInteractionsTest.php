<?php

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('church members can comment and keep one reaction per post', function () {
    $this->withoutVite();
    $church = Church::query()->create(['name' => 'Editorial Church', 'slug' => 'editorial-church']);
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($author);
    $church->assignMember($member);
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Community story',
        'slug' => 'community-story',
        'content' => 'A formatted **story**.',
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($member)->postJson('/api/comments', [
        'commentable_type' => 'post',
        'commentable_id' => $post->id,
        'content' => 'Amen!',
    ])->assertCreated();

    foreach (['like', 'heart'] as $reaction) {
        $this->actingAs($member)->postJson('/api/reactions', [
            'reactionable_type' => 'post',
            'reactionable_id' => $post->id,
            'content' => $reaction,
            'type' => 'emoji',
        ])->assertCreated();
    }

    expect(Comment::query()->where('commentable_id', $post->id)->count())->toBe(1)
        ->and(Reaction::query()->where('reactionable_id', $post->id)->count())->toBe(1)
        ->and(Reaction::query()->where('reactionable_id', $post->id)->value('content'))->toBe('heart');

    $this->get(route('posts.public.show', ['slug' => $post->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/PublicShow')
            ->where('canInteract', true)
            ->where('post.metrics.comments_count', 1)
            ->where('post.metrics.reactions_count', 1)
            ->has('comments', 1)
            ->has('reactions', 1));
});

test('content managers can maintain church categories while members cannot', function () {
    $church = Church::query()->create(['name' => 'Category Church', 'slug' => 'category-church']);
    $manager = User::factory()->create(['role' => UserRole::MEDIA]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($manager);
    $church->assignMember($member);

    $this->actingAs($manager)->postJson('/api/categories', [
        'name' => 'News',
        'slug' => 'news',
        'type' => CategoryType::POST->value,
    ])->assertCreated();

    $this->actingAs($member)->postJson('/api/categories', [
        'name' => 'Unauthorized',
        'slug' => 'unauthorized',
        'type' => CategoryType::POST->value,
    ])->assertForbidden();

    $this->assertDatabaseHas('categories', [
        'church_id' => $church->id,
        'slug' => 'news',
        'type' => CategoryType::POST->value,
    ]);
});
