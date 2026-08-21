<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Library;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function createLibraryVerseAdmin(): User
{
    $community = Community::query()->create([
        'name' => 'Comunidade Biblioteca '.fake()->unique()->word(),
        'description' => 'Comunidade de testes',
        'slug' => fake()->unique()->slug(),
    ]);

    $church = Church::query()->create([
        'name' => 'Igreja Biblioteca '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'community_id' => $community->id,
        'status' => 'active',
    ]);

    $admin = User::factory()->create([
        'role' => UserRole::CHURCH_LEADER,
    ]);

    UserProfile::query()->create([
        'user_id' => $admin->id,
        'church_id' => $church->id,
        'location_lang' => 'pt-BR',
    ]);

    return $admin;
}

it('supports library verse create update delete flows for admins', function () {
    $admin = createLibraryVerseAdmin();
    Storage::fake('public');
    Cache::clear();
    Http::fake(function (Request $request) {
        $url = rawurldecode($request->url());

        return match (true) {
            str_ends_with($url, '/pt-BR-blt/books') => Http::response([
                ['name' => 'filipenses', 'type' => 'dir'],
            ]),
            str_ends_with($url, '/pt-BR-blt/books/filipenses/chapters') => Http::response([
                ['name' => '4.json', 'type' => 'file'],
            ]),
            str_ends_with($url, '/pt-BR-blt/books/filipenses/chapters/4.json') => Http::response([
                'data' => [
                    ['book' => 'Filipenses', 'chapter' => '4', 'verse' => '13', 'text' => 'Tudo posso naquele que me fortalece.'],
                ],
            ]),
            default => Http::response([], 404),
        };
    });

    $this->actingAs($admin)
        ->put(route('admin.libraryVerse.verse.update'), [
            'book' => 'filipenses',
            'chapter' => 4,
            'verse' => 13,
            'version' => 'pt-BR-blt',
        ])
        ->assertRedirect();

    expect(data_get(Setting::query()->value('options'), 'bible.daily_verse.content'))
        ->toBe('Tudo posso naquele que me fortalece.');

    $this->actingAs($admin)
        ->post(route('admin.libraryVerse.library.store'), [
            'title' => 'Fundamentos da Fe Crista',
            'description' => 'Material de estudo',
            'type' => 'Estudos Biblicos',
            'file_path' => UploadedFile::fake()->create('fundamentos.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect();

    $library = Library::query()->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.libraryVerse.library.update', $library), [
            'title' => 'Fundamentos da Fe Crista - Revisado',
            'description' => 'Material atualizado',
            'type' => 'Estudos Biblicos',
            'file_path' => UploadedFile::fake()->create('fundamentos-v2.pdf', 150, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('libraries', [
        'id' => $library->id,
        'title' => 'Fundamentos da Fe Crista - Revisado',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.libraryVerse.library.destroy', $library))
        ->assertRedirect();

    $this->assertDatabaseMissing('libraries', [
        'id' => $library->id,
    ]);
});
