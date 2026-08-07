<?php

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Community;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;

test('the relation tester graph can be persisted and traversed', function () {
    $community = Community::create([
        'name' => 'Nossa Comunidade',
        'description' => 'Comunidade de teste',
        'slug' => 'nossa-comunidade',
    ]);

    $church = Church::create([
        'name' => 'Nossa Casa',
        'slug' => 'nossa-casa',
        'community_id' => $community->id,
    ]);

    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $teacher = User::factory()->create(['role' => UserRole::LEADER]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $member->profile()->create(['church_id' => $church->id]);

    $postCategory = Category::create([
        'church_id' => $church->id,
        'name' => 'News',
        'slug' => 'news',
        'type' => CategoryType::POST,
    ]);
    $eventCategory = Category::create([
        'church_id' => $church->id,
        'name' => 'Event',
        'slug' => 'event',
        'type' => CategoryType::EVENT,
    ]);
    $classroomCategory = Category::create([
        'church_id' => $church->id,
        'name' => 'Classroom',
        'slug' => 'classroom',
        'type' => CategoryType::CLASSROOM,
    ]);

    $post = Post::create([
        'title' => 'Post de teste',
        'slug' => 'post-de-teste',
        'content' => 'Conteudo de teste',
        'published_at' => now(),
        'author_id' => $author->id,
        'church_id' => $church->id,
    ]);
    $post->categories()->attach($postCategory);
    $comment = $post->comments()->create([
        'user_id' => $member->id,
        'content' => 'Comentario de teste',
    ]);
    $reply = $comment->replies()->create([
        'user_id' => $teacher->id,
        'content' => 'Resposta de teste',
    ]);
    $post->reactions()->create([
        'user_id' => $member->id,
        'type' => 'emoji',
        'content' => 'like',
    ]);

    $classroom = Classroom::create([
        'church_id' => $church->id,
        'teacher_id' => $teacher->id,
        'name' => 'Obreiros',
        'min_age' => 18,
    ]);
    $classroom->categories()->attach($classroomCategory);
    $classroom->members()->attach($member);

    $event = Event::create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Evento de teste',
        'slug' => 'evento-de-teste',
        'tags' => ['teste'],
        'start_time' => now(),
        'end_time' => now()->addDay(),
    ]);
    $event->categories()->attach($eventCategory);
    $event->users()->attach($member, ['status' => 'approved']);
    $event->confirmations()->create([
        'user_id' => $member->id,
        'check_in_at' => now(),
    ]);

    expect($church->fresh()->members->modelKeys())->toContain($member->id)
        ->and($post->fresh()->categories->modelKeys())->toContain($postCategory->id)
        ->and($comment->fresh()->replies->modelKeys())->toContain($reply->id)
        ->and($post->fresh()->reactions)->toHaveCount(1)
        ->and($classroom->fresh()->teacher->is($teacher))->toBeTrue()
        ->and($classroom->fresh()->members->modelKeys())->toContain($member->id)
        ->and($event->fresh()->users->modelKeys())->toContain($member->id)
        ->and($event->fresh()->confirmations)->toHaveCount(1);
});

test('a media category belongs to the media record', function () {
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa']);
    $uploader = User::factory()->create(['role' => UserRole::MEMBER]);
    $category = Category::create([
        'church_id' => $church->id,
        'name' => 'Video',
        'slug' => 'video',
        'type' => CategoryType::MEDIA,
    ]);

    $media = $church->media()->create([
        'uploader_id' => $uploader->id,
        'file_path' => 'gallery/video.mp4',
        'mimetype' => 'video/mp4',
        'size' => 1024,
        'gallery' => true,
        'status' => 'pending',
    ]);
    $media->categories()->attach($category);

    expect($media->fresh()->categories->modelKeys())->toContain($category->id);
});
