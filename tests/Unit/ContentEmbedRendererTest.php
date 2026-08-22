<?php

use App\Models\Church;
use App\Models\Post;
use App\Models\User;
use App\Support\ContentEmbedRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('content shortcode renders a safe preview for church content', function () {
    $church = Church::factory()->create();
    $author = User::factory()->create();
    $post = Post::query()->create([
        'church_id' => $church->id,
        'author_id' => $author->id,
        'title' => 'Linked post',
        'slug' => 'linked-post-'.Str::lower((string) Str::ulid()),
        'content' => 'Preview content',
        'published_at' => now(),
    ]);

    $html = app(ContentEmbedRenderer::class)->render("Before\n\n[[post:{$post->id}]]\n\nAfter", $church->id);

    expect($html)->toContain('content-embed-card')->toContain('Linked post')->toContain('/posts/'.$post->slug);
});
