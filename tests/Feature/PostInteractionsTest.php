<?php

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Comment;
use App\Models\Media;
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

test('users can remove only their own reactions from posts media and comments', function () {
    $church = Church::query()->create(['name' => 'Reaction Church', 'slug' => 'reaction-church']);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $otherMember = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);
    $church->assignMember($otherMember);
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $member->id,
        'title' => 'Reaction target',
        'slug' => 'reaction-target',
        'content' => 'Content',
        'published_at' => now(),
    ]);
    $comment = $post->comments()->create([
        'user_id' => $otherMember->id,
        'content' => 'Comment target',
    ]);
    $media = Media::query()->create([
        'church_id' => $church->id,
        'uploader_id' => $member->id,
        'file_path' => 'https://example.test/reaction.jpg',
        'mimetype' => 'image/jpeg',
        'size' => 1024,
        'gallery' => true,
        'status' => MediaStatus::APPROVED,
    ]);
    $postReaction = $post->reactions()->create([
        'user_id' => $member->id,
        'content' => '❤️',
        'type' => 'emoji',
    ]);
    $commentReaction = $comment->reactions()->create([
        'user_id' => $member->id,
        'content' => '❤️',
        'type' => 'emoji',
    ]);
    $mediaReaction = $media->reactions()->create([
        'user_id' => $member->id,
        'content' => '🎉',
        'type' => 'emoji',
    ]);
    $otherReaction = $post->reactions()->create([
        'user_id' => $otherMember->id,
        'content' => '🙏',
        'type' => 'emoji',
    ]);

    $this->actingAs($member)
        ->deleteJson('/api/reactions/'.$postReaction->id)
        ->assertNoContent();
    $this->actingAs($member)
        ->deleteJson('/api/reactions/'.$commentReaction->id)
        ->assertNoContent();
    $this->actingAs($member)
        ->deleteJson('/api/reactions/'.$mediaReaction->id)
        ->assertNoContent();
    $this->actingAs($member)
        ->deleteJson('/api/reactions/'.$otherReaction->id)
        ->assertForbidden();

    expect(Reaction::query()->whereKey($postReaction->id)->exists())->toBeFalse()
        ->and(Reaction::query()->whereKey($commentReaction->id)->exists())->toBeFalse()
        ->and(Reaction::query()->whereKey($mediaReaction->id)->exists())->toBeFalse()
        ->and(Reaction::query()->whereKey($otherReaction->id)->exists())->toBeTrue();
});
