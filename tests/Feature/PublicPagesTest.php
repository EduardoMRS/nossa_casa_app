<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\Form;
use App\Models\Post;
use App\Models\User;
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
    $church = Church::query()->create(['name' => 'Publishing church', 'slug' => 'publishing-church']);
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

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestPosts.0.slug', $post->slug)
            ->where('latestPosts.0.excerpt', 'Main heading A strong update.alert(1)'));

    $this->get(route('posts.public.show', ['slug' => $post->slug]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Posts/PublicShow')
            ->where('post.title', 'Formatted update')
            ->where('post.contentHtml', fn (string $html) => str_contains($html, '<h1>Main heading</h1>')
                && str_contains($html, '<strong>strong</strong>')
                && ! str_contains($html, '<script>')));
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
