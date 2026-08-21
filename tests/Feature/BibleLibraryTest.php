<?php

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\Library;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vercicle;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Cache::clear();
    config(['app.url' => 'http://platform.test']);

    Http::fake(function (Request $request) {
        $url = rawurldecode($request->url());

        return match (true) {
            str_ends_with($url, '/almeida.json') => Http::response([
                'translation' => 'Almeida Atualizada',
                'abbreviation' => 'almeida',
                'books' => [
                    [
                        'nr' => 43,
                        'name' => 'João',
                        'chapters' => [
                            [
                                'chapter' => 3,
                                'verses' => [
                                    ['chapter' => 3, 'verse' => 16, 'text' => 'Porque Deus amou o mundo.'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            str_ends_with($url, '/bibles.json') => Http::response([
                [
                    'id' => 'pt-BR-blt',
                    'version' => 'Biblia Livre Para Todos',
                    'localVersionName' => 'Bíblia Livre Para Todos',
                    'localVersionAbbreviation' => 'BLT',
                    'scope' => 'Bible',
                    'language' => ['name' => 'Portuguese', 'code' => 'por'],
                    'copyright' => '',
                ],
                [
                    'id' => 'en-kjv',
                    'version' => 'King James Version',
                    'localVersionName' => 'King James Version',
                    'localVersionAbbreviation' => 'KJV',
                    'scope' => 'Bible',
                    'language' => ['name' => 'English', 'code' => 'eng'],
                    'copyright' => 'PUBLIC DOMAIN',
                ],
            ]),
            str_ends_with($url, '/pt-BR-blt/books') => Http::response([
                ['name' => 'joão', 'type' => 'dir'],
            ]),
            str_ends_with($url, '/pt-BR-blt/books/joão/chapters') => Http::response([
                ['name' => '1.json', 'type' => 'file'],
                ['name' => '3.json', 'type' => 'file'],
                ['name' => '3', 'type' => 'dir'],
            ]),
            str_ends_with($url, '/pt-BR-blt/books/joão/chapters/3.json') => Http::response([
                'data' => [
                    ['book' => 'João', 'chapter' => '3', 'verse' => '16', 'text' => 'Porque Deus amou o mundo.'],
                    ['book' => 'João', 'chapter' => '3', 'verse' => '17', 'text' => 'Deus enviou o Filho para salvar.'],
                ],
            ]),
            default => Http::response([], 404),
        };
    });
});

function createBibleChurch(User $owner): Church
{
    $community = Community::factory()->create([
        'owner_id' => $owner->id,
        'bible_versions' => ['pt-almeida-1911', 'pt-BR-blt', 'en-kjv'],
        'default_bible_version' => 'pt-BR-blt',
    ]);

    return Church::factory()->create([
        'community_id' => $community->id,
        'domain' => 'bible-church.test',
    ]);
}

test('the public library exposes one virtual Bible with community versions', function () {
    $owner = User::factory()->create();
    $church = createBibleChurch($owner);
    Library::query()->create([
        'church_id' => $church->id,
        'title' => 'Welcome guide',
        'description' => 'A local resource.',
        'type' => 'guide',
    ]);

    $this->get('http://bible-church.test/biblioteca')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->where('bible.versions_count', 3)
            ->has('items.data', 1));

    $this->get('http://bible-church.test/biblioteca/biblia')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Bible')
            ->where('defaultVersion', 'pt-BR-blt')
            ->has('versions', 3)
            ->where('versions.1.offline_available', true)
            ->where('versions.1.offline_url', '/api/bible/pt-almeida-1911/offline'));
});

test('an enabled open Bible version exposes a normalized offline bundle', function () {
    $owner = User::factory()->create();
    createBibleChurch($owner);

    $this->getJson('http://bible-church.test/api/bible/pt-almeida-1911/offline')
        ->assertOk()
        ->assertHeader('X-Bible-Offline-Allowed', '1')
        ->assertJsonPath('version.id', 'pt-almeida-1911')
        ->assertJsonPath('books.0.slug', 'joao')
        ->assertJsonPath('books.0.chapters.0.verses.0.text', 'Porque Deus amou o mundo.');

    $this->getJson('http://bible-church.test/api/bible/pt-BR-blt/offline')
        ->assertNotFound();
});

test('Bible navigation only proxies versions enabled for the current church', function () {
    $owner = User::factory()->create();
    createBibleChurch($owner);

    $this->getJson('http://bible-church.test/api/bible/pt-BR-blt/books')
        ->assertOk()
        ->assertHeader('X-Bible-Offline-Allowed', '0')
        ->assertJsonPath('books.0.slug', 'joão');
    $this->getJson('http://bible-church.test/api/bible/pt-BR-blt/books/jo%C3%A3o/chapters')
        ->assertOk()
        ->assertExactJson(['chapters' => [1, 3]]);
    $this->getJson('http://bible-church.test/api/bible/pt-BR-blt/books/jo%C3%A3o/chapters/3')
        ->assertOk()
        ->assertJsonPath('verses.0.text', 'Porque Deus amou o mundo.');
    $this->getJson('http://bible-church.test/api/bible/es-unknown/books')
        ->assertNotFound();
});

test('daily verse is copied from the source API without creating or translating a Bible record', function () {
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church = createBibleChurch($leader);
    $church->assignMember($leader);

    $this->actingAs($leader)->put('http://bible-church.test/dashboard/biblioteca-versiculo/verse', [
        'version' => 'pt-BR-blt',
        'book' => 'joão',
        'chapter' => 3,
        'verse' => 16,
        'content' => 'A client must not define Scripture.',
    ])->assertRedirect();

    $dailyVerse = data_get(Setting::query()->where('church_id', $church->id)->value('options'), 'bible.daily_verse');

    expect($dailyVerse['content'])->toBe('Porque Deus amou o mundo.')
        ->and($dailyVerse['book_name'])->toBe('João')
        ->and(Library::query()->where('church_id', $church->id)->where('type', 'Versiculo do Dia')->exists())->toBeFalse()
        ->and(Vercicle::query()->exists())->toBeFalse();

    $this->get('http://bible-church.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dailyVerse.content', 'Porque Deus amou o mundo.')
            ->where('dailyVerse.version', 'pt-BR-blt'));
});

test('a church can only select versions approved by its community', function () {
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    createBibleChurch($leader)->assignMember($leader);

    $this->actingAs($leader)->put('http://bible-church.test/dashboard/biblioteca-versiculo/bible', [
        'scope' => 'community',
        'versions' => ['pt-BR-blt'],
        'default_version' => 'pt-BR-blt',
    ])->assertRedirect();

    $this->put('http://bible-church.test/dashboard/biblioteca-versiculo/bible', [
        'scope' => 'church',
        'versions' => ['en-kjv'],
        'default_version' => 'en-kjv',
    ])->assertStatus(422);

    $this->put('http://bible-church.test/dashboard/biblioteca-versiculo/bible', [
        'scope' => 'church',
        'versions' => ['pt-BR-blt'],
        'default_version' => 'pt-BR-blt',
    ])->assertRedirect();
});
