<?php

use App\Console\Commands\ApiTranslateCommand;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Event;
use App\Models\Form;
use App\Models\Translation;
use App\Models\User;
use App\Services\AiProvider;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('app.locales', ['en', 'pt']);
});

function createLocalizedEventFixtures(): array
{
    $church = Church::query()->create([
        'name' => 'Original Church',
        'slug' => 'original-church',
    ]);
    $leader = User::factory()->create(['role' => UserRole::LEADER]);
    $church->assignMember($leader);
    $event = Event::query()->create([
        'church_id' => $church->id,
        'author_id' => $leader->id,
        'title' => 'Original Event',
        'slug' => 'original-event',
        'description' => 'Original event description.',
        'start_time' => now()->addDay(),
        'end_time' => now()->addDay()->addHours(2),
    ]);
    $form = Form::query()->create([
        'church_id' => $church->id,
        'title' => 'Original Form',
        'description' => 'Original form description.',
        'schema' => [
            'fields' => [[
                'id' => 'full-name',
                'name' => 'full_name',
                'type' => 'text',
                'label' => 'Full name',
            ]],
        ],
    ]);
    $event->forms()->attach($form->id);

    foreach ([
        [$event, 'title', 'Evento traduzido'],
        [$event, 'description', 'Descrição traduzida do evento.'],
        [$form, 'title', 'Formulário traduzido'],
        [$form, 'description', 'Descrição traduzida do formulário.'],
        [$form, 'schema', json_encode([
            'fields' => [[
                'id' => 'full-name',
                'name' => 'full_name',
                'type' => 'text',
                'label' => 'Nome completo',
            ]],
        ], JSON_THROW_ON_ERROR)],
    ] as [$model, $column, $content]) {
        Translation::query()->create([
            'translatable_type' => $model->getMorphClass(),
            'translatable_id' => $model->getKey(),
            'translatable_column' => $column,
            'locale' => 'pt',
            'content_original' => (string) $model->getRawOriginal($column),
            'content' => $content,
        ]);
    }

    Translation::query()->create([
        'translatable_type' => $event->getMorphClass(),
        'translatable_id' => $event->getKey(),
        'translatable_column' => 'title',
        'locale' => 'en',
        'content_original' => $event->getRawOriginal('title'),
        'content' => 'English override',
    ]);

    return compact('church', 'leader', 'event', 'form');
}

test('translations attribute returns only content for the current locale', function () {
    ['event' => $event] = createLocalizedEventFixtures();

    App::setLocale('pt-BR');
    $translations = $event->fresh()->translations;

    expect($translations)
        ->toBe([
            'title' => 'Evento traduzido',
            'description' => 'Descrição traduzida do evento.',
        ])
        ->not->toContain('English override');

    $event->update(['title' => 'Updated original event']);

    expect($event->fresh()->translations)
        ->not->toHaveKey('title')
        ->toHaveKey('description', 'Descrição traduzida do evento.');
});

test('public index and show responses use the requested content locale', function () {
    ['event' => $event] = createLocalizedEventFixtures();

    $this->withUnencryptedCookie('ncapp_locale', 'pt')
        ->get('/pt/events')
        ->assertSuccessful()
        ->assertSee('<html lang="pt"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'pt')
            ->where('events.data.0.title', 'Evento traduzido')
            ->where('events.data.0.description', 'Descrição traduzida do evento.')
        );

    $this->withHeader('X-Locale', 'pt-BR')
        ->getJson("/api/event/{$event->slug}")
        ->assertSuccessful()
        ->assertJsonPath('title', 'Evento traduzido')
        ->assertJsonPath('description', 'Descrição traduzida do evento.');
});

test('registration page localizes form schema while edit pages keep original content', function () {
    ['leader' => $leader, 'event' => $event] = createLocalizedEventFixtures();

    $this->withUnencryptedCookie('ncapp_locale', 'pt')
        ->get("/pt/events/{$event->slug}/register")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.title', 'Evento traduzido')
            ->where('form.title', 'Formulário traduzido')
            ->where('form.schema.fields.0.label', 'Nome completo')
            ->where('form.schema.fields.0.name', 'full_name')
            ->where('form.schema.fields.0.type', 'text')
        );

    $this->actingAs($leader)
        ->withUnencryptedCookie('ncapp_locale', 'pt')
        ->get(route('events.edit', ['event' => $event->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.title', 'Original Event')
            ->where('event.description', 'Original event description.')
        );
});

test('translation command covers dynamic text and form schema fields', function () {
    ['form' => $form] = createLocalizedEventFixtures();

    Translation::query()->delete();

    $fakeProvider = new class extends AiProvider
    {
        public int $runCount = 0;

        private string $prompt = '';

        public function __construct()
        {
            $this->prompt = '';
        }

        public function system(string $content): self
        {
            $this->prompt = $content;

            return $this;
        }

        public function run(): array
        {
            $this->runCount++;
            preg_match('/translate it to ([^.]+)\./', $this->prompt, $localeMatch);
            $locale = trim($localeMatch[1] ?? 'en');
            $payload = json_decode(str($this->prompt)->afterLast('Payload: ')->toString(), true, flags: JSON_THROW_ON_ERROR);

            $items = collect($payload['items'])->map(function (array $item) use ($locale): array {
                $content = $item['content_original'];

                if ($locale === 'pt') {
                    $content = $item['translatable_column'] === 'schema'
                        ? str_replace('Full name', 'Nome completo', $content)
                        : 'PT: '.$content;
                }

                return [
                    'translatable_type' => $item['translatable_type'],
                    'translatable_id' => $item['translatable_id'],
                    'translatable_column' => $item['translatable_column'],
                    'content' => $content,
                ];
            })->all();

            $encodedItems = json_encode(['items' => $items], JSON_THROW_ON_ERROR);

            return ['content' => "Translation completed:\n```json\n{$encodedItems}\n```"];
        }
    };

    $this->app->instance(AiProvider::class, $fakeProvider);

    $this->artisan('lang:translate --dynamic-only')->assertSuccessful();
    $firstRunCount = $fakeProvider->runCount;

    expect($firstRunCount)->toBeGreaterThan(0);

    $this->artisan('lang:translate --dynamic-only')->assertSuccessful();

    expect($fakeProvider->runCount)->toBe($firstRunCount);

    $form->update(['title' => 'Updated Form']);

    $this->artisan('lang:translate --dynamic-only')->assertSuccessful();

    expect($fakeProvider->runCount)->toBe($firstRunCount + 2);

    $schemaTranslation = Translation::query()
        ->where('translatable_type', $form->getMorphClass())
        ->where('translatable_id', $form->id)
        ->where('translatable_column', 'schema')
        ->where('locale', 'pt')
        ->firstOrFail();

    expect(json_decode($schemaTranslation->content, true, flags: JSON_THROW_ON_ERROR))
        ->toHaveKey('fields.0.label', 'Nome completo')
        ->toHaveKey('fields.0.name', 'full_name')
        ->toHaveKey('fields.0.type', 'text');

    expect(Translation::query()
        ->where('translatable_type', $form->getMorphClass())
        ->where('translatable_id', $form->id)
        ->where('translatable_column', 'title')
        ->where('locale', 'pt')
        ->firstOrFail()
        ->only(['content_original', 'content']))
        ->toBe([
            'content_original' => 'Updated Form',
            'content' => 'PT: Updated Form',
        ]);
});

test('translation command extracts JSON returned in a reasoning response', function () {
    $fakeProvider = new class extends AiProvider
    {
        private int $runCount = 0;

        public function __construct()
        {
            $this->runCount = 0;
        }

        public function system(string $content): self
        {
            return $this;
        }

        public function run(): array
        {
            return [
                'provider' => 'fake',
                'model' => 'fake-model',
                'content' => '',
                'raw_response' => [
                    'choices' => [[
                        'message' => [
                            'reasoning' => "I will return the requested JSON.\n```json\n{\"pt\":{\"admin.php\":{\"title\":\"Igreja\"}}}\n```",
                        ],
                    ]],
                ],
            ];
        }
    };

    $this->app->instance(AiProvider::class, $fakeProvider);
    $method = new ReflectionMethod(ApiTranslateCommand::class, 'runTranslationPrompt');
    $method->setAccessible(true);

    expect($method->invoke(app(ApiTranslateCommand::class), 'Translate this payload.'))
        ->toBe([
            'pt' => [
                'admin.php' => [
                    'title' => 'Igreja',
                ],
            ],
        ]);
});
