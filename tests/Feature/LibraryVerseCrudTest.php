<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Library;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
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

    $this->actingAs($admin)
        ->put(route('admin.libraryVerse.verse.update'), [
            'book' => 'Filipenses',
            'chapter' => 4,
            'verse' => 13,
            'content' => 'Tudo posso naquele que me fortalece.',
            'version' => 'Almeida Revista e Corrigida',
        ])
        ->assertRedirect();

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
