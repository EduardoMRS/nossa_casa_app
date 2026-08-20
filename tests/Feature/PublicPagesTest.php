<?php

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Church;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Form;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can visit public home page', function () {
    $this->withoutVite();

    $response = $this->get(route('home'));

    $response->assertOk();
});

test('guests can visit public events page', function () {
    $this->withoutVite();
    $church = Church::query()->create(['name' => 'Events church', 'slug' => 'events-church']);
    $author = User::factory()->create(['role' => UserRole::LEADER]);
    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Listed public event',
        'slug' => 'listed-public-event',
        'description' => 'Public event description',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
    ]);

    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Index')
            ->where('events.data.0.id', $event->id));
});

test('guests can visit public gallery page', function () {
    $this->withoutVite();

    $response = $this->get(route('gallery.index'));

    $response->assertOk();
});

test('home links to a public post page with rendered markdown', function () {
    $this->withoutVite();
    $church = Church::query()->create(['name' => 'Publishing church', 'slug' => 'publishing-church', 'domain' => 'publishing.test']);
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($author);
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Formatted update',
        'slug' => 'formatted-update',
        'content' => '# Main heading'.PHP_EOL.PHP_EOL.'A **strong** update.<script>alert(1)</script>',
        'published_at' => now()->subMinute(),
    ]);

    $this->get('http://publishing.test/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestPosts.0.slug', $post->slug)
            ->where('latestPosts.0.excerpt', 'Main heading A strong update.alert(1)'));

    $this->get('http://publishing.test/posts')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/PublicIndex')
            ->where('posts.data.0.slug', $post->slug)
            ->where('posts.data.0.comments_count', 0)
            ->where('posts.data.0.reactions_count', 0));

    $this->get('http://publishing.test/posts/'.$post->slug)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/PublicShow')
            ->where('post.title', 'Formatted update')
            ->where('post.views_count', 1)
            ->has('relatedPosts', 0)
            ->has('latestPosts', 0)
            ->where('post.contentHtml', fn (string $html) => str_contains($html, '<h1>Main heading</h1>')
                && str_contains($html, '<strong>strong</strong>')
                && ! str_contains($html, '<script>')));

    expect($post->fresh()->views_count)->toBe(1);
});

test('public posts support blog filters and related content', function () {
    $this->withoutVite();
    $church = Church::query()->create([
        'name' => 'Blog church',
        'slug' => 'blog-church',
        'domain' => 'blog.test',
    ]);
    $author = User::factory()->create(['role' => UserRole::MEDIA]);
    $church->assignMember($author);
    $category = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Community News',
        'slug' => 'community-news',
        'type' => CategoryType::POST,
    ]);
    $featuredPost = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Mission outreach report',
        'slug' => 'mission-outreach-report',
        'content' => 'The outreach reached the whole neighborhood.',
        'published_at' => now()->subDay(),
        'views_count' => 42,
    ]);
    $featuredPost->categories()->attach($category->id);
    $relatedPost = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'A second mission story',
        'slug' => 'second-mission-story',
        'content' => 'Another community report.',
        'published_at' => now()->subHours(2),
        'views_count' => 8,
    ]);
    $relatedPost->categories()->attach($category->id);
    Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Unrelated archived note',
        'slug' => 'unrelated-archived-note',
        'content' => 'This should not match the search.',
        'published_at' => now()->subMonth(),
    ]);

    $this->get('http://blog.test/posts?'.http_build_query([
        'search' => 'outreach',
        'category' => $category->id,
        'date_from' => now()->subDays(2)->toDateString(),
        'date_to' => now()->toDateString(),
        'sort' => 'popular',
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/PublicIndex')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $featuredPost->id)
            ->where('posts.data.0.views_count', 42)
            ->where('filters.category', $category->id)
            ->where('filters.sort', 'popular')
            ->where('categories', fn (Collection $categories): bool => $categories
                ->contains(fn (array $item): bool => $item['id'] === $category->id))
            ->where('mostViewed.0.id', $featuredPost->id));

    $this->get('http://blog.test/posts/'.$featuredPost->slug)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('relatedPosts.0.id', $relatedPost->id)
            ->where('latestPosts.0.id', $relatedPost->id));
});

test('public gallery exposes social media details for the church domain', function () {
    $this->withoutVite();
    $church = Church::query()->create([
        'name' => 'Gallery church',
        'slug' => 'gallery-church',
        'domain' => 'gallery.test',
    ]);
    $uploader = User::factory()->create(['role' => UserRole::MEDIA]);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($uploader);
    $church->assignMember($member);
    $media = Media::query()->create([
        'church_id' => $church->id,
        'uploader_id' => $uploader->id,
        'title' => 'Sunday celebration',
        'description' => 'A special moment from our Sunday service.',
        'file_path' => 'https://example.test/sunday.jpg',
        'mimetype' => 'image/jpeg',
        'size' => 1024,
        'gallery' => true,
        'status' => MediaStatus::APPROVED,
    ]);
    $comment = $media->comments()->create([
        'user_id' => $member->id,
        'content' => 'A beautiful celebration!',
    ]);
    $media->reactions()->create([
        'user_id' => $member->id,
        'content' => '❤️',
        'type' => 'emoji',
    ]);
    $comment->reactions()->create([
        'user_id' => $uploader->id,
        'content' => '❤️',
        'type' => 'emoji',
    ]);

    $this->actingAs($member)->postJson('/api/comments', [
        'commentable_type' => 'media',
        'commentable_id' => $media->id,
        'content' => 'Another memory from this day.',
    ])->assertCreated();
    $newComment = Comment::query()
        ->where('commentable_id', $media->id)
        ->where('content', 'Another memory from this day.')
        ->firstOrFail();

    $this->actingAs($member)->postJson('/api/reactions', [
        'reactionable_type' => 'media',
        'reactionable_id' => $media->id,
        'content' => '🎉',
        'type' => 'emoji',
    ])->assertCreated();
    $this->actingAs($member)->postJson('/api/reactions', [
        'reactionable_type' => 'comment',
        'reactionable_id' => $newComment->id,
        'content' => '❤️',
        'type' => 'emoji',
    ])->assertCreated();

    $this->actingAs($member)
        ->get('http://gallery.test/gallery')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Gallery/Index')
            ->where('canInteract', true)
            ->where('media.data.0.title', 'Sunday celebration')
            ->where('media.data.0.description', 'A special moment from our Sunday service.')
            ->has('media.data.0.comments', 2)
            ->has('media.data.0.comments.0.reactions', 1)
            ->has('media.data.0.reactions', 1)
            ->where('media.data.0.reactions.0.content', '🎉'));
});

test('guests can visit public event detail and register pages', function () {
    $this->withoutVite();

    $church = Church::query()->create([
        'name' => 'Igreja Central',
        'slug' => 'igreja-central',
    ]);

    $author = User::factory()->create([
        'role' => UserRole::LEADER,
    ]);

    $author->profile()->create([
        'church_id' => $church->id,
    ]);

    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Conferencia de Jovens',
        'slug' => 'conferencia-jovens',
        'description' => '# Programacao',
        'start_time' => now()->addDays(5),
        'end_time' => now()->addDays(5)->addHours(3),
    ]);

    $form = Form::query()->create([
        'title' => 'Inscricao',
        'description' => 'Formulario oficial',
        'church_id' => $church->id,
        'schema' => [
            [
                'name' => 'full_name',
                'label' => 'Nome completo',
                'type' => 'text',
                'required' => true,
            ],
        ],
    ]);

    $event->forms()->attach($form->id);

    $this->get(route('events.show', ['event' => $event->slug]))->assertOk();
    $this->get(route('events.register', ['event' => $event->slug]))->assertOk();
});
