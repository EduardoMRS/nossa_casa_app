<?php

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Church;
use App\Models\Event;
use App\Models\Form;
use App\Models\Library;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('content controllers sync categories for their church and type', function () {
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($leader);
    $otherChurch = Church::create(['name' => 'Outra Casa', 'slug' => 'outra-casa', 'status' => 'active']);

    $eventCategory = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Culto',
        'slug' => 'culto',
        'type' => CategoryType::EVENT->value,
    ]);

    $postCategory = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Devocional',
        'slug' => 'devocional',
        'type' => CategoryType::POST->value,
    ]);

    $foreignEventCategory = Category::query()->create([
        'church_id' => $otherChurch->id,
        'name' => 'Culto externo',
        'slug' => 'culto-externo',
        'type' => CategoryType::EVENT->value,
    ]);

    $mediaCategory = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Fotos',
        'slug' => 'fotos',
        'type' => CategoryType::MEDIA->value,
    ]);

    $libraryCategory = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Estudo',
        'slug' => 'estudo',
        'type' => CategoryType::LIBRARY->value,
    ]);

    $formCategory = Category::query()->create([
        'church_id' => $church->id,
        'name' => 'Inscricao',
        'slug' => 'inscricao',
        'type' => CategoryType::FORM->value,
    ]);

    $eventResponse = $this->actingAs($leader)->postJson('/api/event', [
        'title' => 'Culto da familia',
        'slug' => 'culto-da-familia',
        'description' => 'Evento de teste',
        'start_time' => now()->addDay()->toDateTimeString(),
        'end_time' => now()->addDays(2)->toDateTimeString(),
        'category_ids' => [$eventCategory->id, $postCategory->id, $foreignEventCategory->id],
    ]);

    $eventResponse->assertCreated();

    $event = Event::query()->where('slug', 'culto-da-familia')->firstOrFail();
    expect($event->categories()->pluck('categories.id')->all())->toBe([$eventCategory->id]);

    $postResponse = $this->actingAs($leader)->postJson('/api/post', [
        'title' => 'Post de teste',
        'slug' => 'post-de-teste',
        'content' => 'Conteudo',
        'published_at' => now()->toDateTimeString(),
        'category_ids' => [$postCategory->id, $eventCategory->id],
    ]);

    $postResponse->assertCreated();

    $post = Post::query()->where('slug', 'post-de-teste')->firstOrFail();
    expect($post->categories()->pluck('categories.id')->all())->toBe([$postCategory->id]);

    $mediaResponse = $this->actingAs($leader)->postJson('/api/media', [
        'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        'gallery' => true,
        'category_ids' => [$mediaCategory->id, $postCategory->id],
    ]);

    $mediaResponse->assertCreated();

    $media = Media::query()->findOrFail($mediaResponse->json('id'));
    expect($media->categories()->pluck('categories.id')->all())->toBe([$mediaCategory->id]);

    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($admin);

    $librarySource = storage_path('app/testing-library.pdf');
    file_put_contents($librarySource, '%PDF-1.4 testing');

    try {
        $this->actingAs($admin)->post('/dashboard/biblioteca-versiculo/library', [
            'title' => 'Biblioteca de teste',
            'description' => 'Arquivo de teste',
            'type' => 'book',
            'file_path' => $librarySource,
            'category_ids' => [$libraryCategory->id, $formCategory->id],
        ])->assertRedirect();

        $library = Library::query()->where('title', 'Biblioteca de teste')->firstOrFail();
        expect($library->categories()->pluck('categories.id')->all())->toBe([$libraryCategory->id]);
    } finally {
        @unlink($librarySource);
    }

    $this->actingAs($leader)->postJson('/api/forms', [
        'title' => 'Formulario de teste',
        'description' => 'Teste',
        'schema' => [
            'fields' => [[
                'id' => 'name',
                'name' => 'name',
                'type' => 'text',
                'label' => 'Nome',
            ]],
        ],
        'category_ids' => [$formCategory->id, $libraryCategory->id],
    ])->assertCreated();

    $form = Form::query()->where('title', 'Formulario de teste')->firstOrFail();
    expect($form->categories()->pluck('categories.id')->all())->toBe([$formCategory->id]);
});
