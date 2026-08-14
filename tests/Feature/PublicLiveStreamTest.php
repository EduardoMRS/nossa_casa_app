<?php

use App\Enums\LiveStreamStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Comment;
use App\Models\LiveStream;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('church home exposes its active transmission widget and guests can watch comments', function () {
    $this->withoutVite();
    $church = Church::query()->create([
        'name' => 'Live Church',
        'slug' => 'live-church',
        'domain' => 'live-church.test',
        'status' => 'active',
    ]);
    $creator = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($creator);
    $liveStream = LiveStream::factory()->create([
        'church_id' => $church->id,
        'created_by_id' => $creator->id,
        'status' => LiveStreamStatus::LIVE,
        'started_at' => now(),
    ]);
    Comment::query()->create([
        'user_id' => $creator->id,
        'commentable_type' => LiveStream::class,
        'commentable_id' => $liveStream->id,
        'content' => 'Welcome to the service!',
    ]);

    $this->get('http://live-church.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('activeLiveStream.id', $liveStream->id));

    $this->get("http://live-church.test/transmissoes/{$liveStream->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LiveStreams/Show')
            ->where('canComment', false)
            ->where('comments.0.content', 'Welcome to the service!'));
});

test('members comment and church moderators pin or remove live comments', function () {
    $church = Church::query()->create([
        'name' => 'Moderated Church',
        'slug' => 'moderated-church',
        'domain' => 'moderated.test',
        'status' => 'active',
    ]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church->assignMember($member);
    $church->assignMember($leader);
    $liveStream = LiveStream::factory()->create([
        'church_id' => $church->id,
        'created_by_id' => $leader->id,
        'status' => LiveStreamStatus::LIVE,
    ]);

    $this->actingAs($member)->postJson('/api/comments', [
        'commentable_type' => 'live_stream',
        'commentable_id' => $liveStream->id,
        'content' => 'Amen!',
    ])->assertCreated();

    $comment = Comment::query()->firstOrFail();

    $this->actingAs($leader)->putJson("/api/comments/{$comment->id}/pin", [
        'is_pinned' => true,
    ])->assertSuccessful()->assertJsonPath('is_pinned', true);

    expect($comment->refresh())
        ->is_pinned->toBeTrue()
        ->pinned_by_id->toBe($leader->id);

    $this->actingAs($leader)->deleteJson("/api/comments/{$comment->id}")->assertNoContent();
    expect(Comment::query()->count())->toBe(0);
});
