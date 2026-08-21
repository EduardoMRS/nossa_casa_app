<?php

use App\Enums\ChurchStatus;
use App\Models\Church;
use App\Models\Community;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    config(['app.url' => 'http://platform.test']);
    $this->withoutVite();
});

test('portal sitemap indexes public communities and active church domains', function () {
    $community = Community::factory()->create(['slug' => 'indexed-community']);
    Church::factory()->for($community)->create([
        'domain' => 'indexed-church.test',
        'status' => ChurchStatus::ACTIVE,
    ]);
    Church::factory()->for($community)->create([
        'domain' => 'inactive-church.test',
        'status' => ChurchStatus::INACTIVE,
    ]);

    $this->get('http://platform.test/sitemap.xml')
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>http://platform.test/</loc>', false)
        ->assertSee('<loc>http://platform.test/communities/indexed-community</loc>', false)
        ->assertSee('<loc>https://indexed-church.test/</loc>', false)
        ->assertDontSee('inactive-church.test');

    $this->get('http://platform.test/robots.txt')
        ->assertSuccessful()
        ->assertSee('Disallow: /dashboard')
        ->assertSee('Sitemap: http://platform.test/sitemap.xml');
});

test('church sitemap indexes public sections events and published posts', function () {
    $church = Church::factory()->create(['domain' => 'search-church.test']);
    $author = User::factory()->create();
    $church->assignMember($author);
    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Indexed event',
        'slug' => 'indexed-event',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHour(),
    ]);
    Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Indexed post',
        'slug' => 'indexed-post',
        'content' => 'Public content',
        'published_at' => now()->subMinute(),
    ]);
    Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Draft post',
        'slug' => 'draft-post',
        'content' => 'Draft content',
    ]);

    $this->get('http://search-church.test/sitemap.xml')
        ->assertSuccessful()
        ->assertSee('<loc>http://search-church.test/events</loc>', false)
        ->assertSee('<loc>http://search-church.test/events/'.$event->slug.'</loc>', false)
        ->assertSee('<loc>http://search-church.test/posts/indexed-post</loc>', false)
        ->assertSee('<loc>http://search-church.test/biblioteca</loc>', false)
        ->assertDontSee('draft-post');
});

test('public pages expose canonical organization metadata', function () {
    $church = Church::factory()->create([
        'name' => 'Metadata Church',
        'domain' => 'metadata-church.test',
    ]);

    $this->get('http://metadata-church.test/')
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="http://metadata-church.test">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('Metadata Church');
});
