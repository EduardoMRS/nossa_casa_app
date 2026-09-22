<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Community;
use App\Models\Event;
use App\Models\Form;
use App\Models\Library;
use App\Models\Post;
use App\Models\Translation;
use App\Services\AiProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Signature('lang:translate {--dynamic-only : Translate database content without rewriting locale files} {--static-only : Translate locale files without rewriting database content}')]
#[Description('Translate static locale files and dynamic application content')]
class ApiTranslateCommand extends Command
{
    private const SOURCE_STATE_FILE = 'app/translation-source-hashes.json';

    private const FRONTEND_TRANSLATION_CHUNK_SIZE = 50;

    private const TRANSLATION_ATTEMPTS = 2;

    /** @var array<class-string, array<int, string>> */
    private const DYNAMIC_CONTENT_FIELDS = [
        Category::class => ['name'],
        Church::class => ['name'],
        Classroom::class => ['name', 'description'],
        Community::class => ['name', 'description'],
        Event::class => ['title', 'description', 'tags'],
        Form::class => ['title', 'description', 'schema'],
        Library::class => ['title', 'description', 'type'],
        Post::class => ['title', 'content'],
    ];

    protected array $backendContentBase = [];

    protected array $frontendContentBase = [];

    /** @var array<string, mixed> */
    private array $translationSourceState = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $locales = array_values(array_unique(array_map(
            fn (string $locale): string => trim($locale),
            config('app.locales') ?? ['en', 'pt']
        )));

        if (empty($locales)) {
            $this->warn('No locales found. Set APP_LOCALES (e.g. en,pt).');

            return self::SUCCESS;
        }

        $staticLocales = array_values(array_filter($locales, fn (string $locale): bool => $locale !== 'en'));

        if (! $this->option('dynamic-only')) {
            $this->translationSourceState = $this->loadTranslationSourceState();
            $this->backendContentBase = $this->loadBackendEnglishFiles();
            $this->frontendContentBase = $this->loadFrontendEnglishJson();

            $this->translateBackendText($staticLocales);
            $this->translateFrontendJson($staticLocales);
        }

        if (! $this->option('static-only')) {
            $this->translateDynamicContent($locales);
        }

        $this->info('Translation command completed successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function loadBackendEnglishFiles(): array
    {
        $files = glob(base_path('lang/en/*.php')) ?: [];
        $content = [];

        foreach ($files as $file) {
            $translations = include $file;
            if (is_array($translations)) {
                $content[basename($file)] = $translations;
            }
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFrontendEnglishJson(): array
    {
        $filePath = base_path('resources/js/locales/en.json');

        if (! file_exists($filePath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($filePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function translateBackendText(array $locales): void
    {
        if (empty($locales)) {
            return;
        }

        if (empty($this->backendContentBase)) {
            $this->warn('No backend lang/en/*.php files were found.');

            return;
        }

        Log::debug('Starting backend translation for locales: '.implode(',', $locales));
        $sourceHash = $this->sourceHash($this->backendContentBase);
        $pendingLocales = array_values(array_filter(
            $locales,
            fn (string $locale): bool => data_get($this->translationSourceState, "backend.{$locale}") !== $sourceHash
                || ! $this->backendLocaleFilesExist($locale),
        ));

        if ($pendingLocales === []) {
            $this->info('Backend PHP lang files unchanged; translation skipped.');

            return;
        }

        $systemPrompt = 'You are a professional translator. Translate this Laravel PHP language payload from English to these locales: '
            .implode(', ', $pendingLocales)
            .'. Return only valid JSON with this exact shape: {"locale": {"filename.php": {...translated keys...}}}. '
            .'Keep all keys and placeholders exactly as provided. Payload: '
            .json_encode(['en' => $this->backendContentBase], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $translatedContent = $this->runTranslationPrompt($systemPrompt);

        unset($translatedContent['en']);

        foreach ($translatedContent as $lang => $files) {
            if (! in_array($lang, $pendingLocales, true) || ! is_array($files)) {
                continue;
            }

            foreach ($files as $filename => $translations) {
                if (! is_array($translations) || ! Str::endsWith((string) $filename, '.php')) {
                    continue;
                }

                $filePath = base_path("lang/{$lang}/{$filename}");
                if (! is_dir(dirname($filePath))) {
                    mkdir(dirname($filePath), 0775, true);
                }
                $content = "<?php\n\nreturn ".var_export($translations, true);
                $content = preg_replace('/array\s*\(/', '[', $content);
                $content = preg_replace('/\)\s*;?\s*$/', '];', $content);
                $content = str_replace('),', '],', $content);
                $content = preg_replace('/;;+/', ';', $content);
                file_put_contents($filePath, $content."\n");
            }

            data_set($this->translationSourceState, "backend.{$lang}", $sourceHash);
        }

        $this->saveTranslationSourceState();
        $this->info('Backend PHP lang files translated.');
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function translateFrontendJson(array $locales): void
    {
        if (empty($locales)) {
            return;
        }

        if (empty($this->frontendContentBase)) {
            $this->warn('No frontend resources/js/locales/en.json was found.');

            return;
        }

        Log::debug('Starting frontend JSON translation for locales: '.implode(',', $locales));
        $sourceTranslations = Arr::where(
            Arr::dot($this->frontendContentBase),
            fn (mixed $value): bool => is_string($value),
        );

        foreach ($locales as $locale) {
            $filePath = base_path("resources/js/locales/{$locale}.json");
            $existingLocale = file_exists($filePath)
                ? json_decode((string) file_get_contents($filePath), true)
                : [];
            $existingTranslations = is_array($existingLocale) ? Arr::dot($existingLocale) : [];
            $translatedValues = array_replace(
                $sourceTranslations,
                array_intersect_key($existingTranslations, $sourceTranslations),
            );
            $pendingTranslations = Arr::where(
                $sourceTranslations,
                fn (string $source, string $key): bool => ! array_key_exists($key, $existingTranslations)
                    || data_get($this->translationSourceState, "frontend.{$locale}.{$key}") !== $this->sourceHash($source),
            );
            $chunks = array_chunk($pendingTranslations, self::FRONTEND_TRANSLATION_CHUNK_SIZE, true);

            if ($chunks === [] && array_diff_key($existingTranslations, $sourceTranslations) === []) {
                $this->info("Frontend JSON locale unchanged; translation skipped: {$locale}.");

                continue;
            }

            foreach ($chunks as $chunkIndex => $chunk) {
                $systemPrompt = 'You are a professional translator. Translate this frontend i18n payload from English to '.$locale.'. '
                    .'Return only valid JSON with the exact shape {"translations":{"original.dot.key":"translated value"}}. '
                    .'Return every key exactly once and keep keys, placeholders such as {count}, HTML and technical values unchanged. '
                    .'Translate only the string values. Payload: '
                    .json_encode(['translations' => $chunk], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $translatedChunk = data_get($this->runTranslationPrompt($systemPrompt), 'translations');

                if (! is_array($translatedChunk)) {
                    throw new \RuntimeException("Frontend translation chunk {$chunkIndex} for {$locale} has an invalid structure.");
                }

                foreach (Arr::dot($translatedChunk) as $key => $translatedValue) {
                    if (! array_key_exists($key, $chunk) || ! is_string($translatedValue) || blank($translatedValue)) {
                        continue;
                    }

                    if (! $this->hasSamePlaceholders($chunk[$key], $translatedValue)) {
                        $this->warn("Skipped {$locale}.{$key}: translated placeholders do not match the source.");

                        continue;
                    }

                    $translatedValues[$key] = $translatedValue;
                    data_set(
                        $this->translationSourceState,
                        "frontend.{$locale}.{$key}",
                        $this->sourceHash($chunk[$key]),
                    );
                }
            }

            if (! is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0775, true);
            }

            file_put_contents(
                $filePath,
                json_encode(Arr::undot($translatedValues), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );

            $this->saveTranslationSourceState();
            $this->info("Frontend JSON locale translated: {$locale} (".count($chunks).' chunks).');
        }
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function translateDynamicContent(array $locales): void
    {
        // TODO: Otimizar para separar por tamanho de texto ao inves de se limitar por chunck pois em alguns casos o texto será muito pequeno e em outros muito grande, o que pode gerar problemas de tradução. Talvez seja interessante separar por tamanho de texto e não por quantidade de registros.
        $translatedCount = 0;

        foreach (self::DYNAMIC_CONTENT_FIELDS as $modelClass => $columns) {
            $modelClass::query()
                ->select(array_merge(['id'], $columns))
                ->chunkById(10, function ($models) use ($modelClass, $columns, $locales, &$translatedCount): void {
                    $morphType = (new $modelClass)->getMorphClass();
                    $existingTranslations = Translation::query()
                        ->where('translatable_type', $morphType)
                        ->whereIn('translatable_id', $models->modelKeys())
                        ->whereIn('translatable_column', $columns)
                        ->whereIn('locale', $locales)
                        ->get()
                        ->groupBy(fn (Translation $translation): string => $this->storedTranslationKey(
                            $translation->translatable_type,
                            (string) $translation->translatable_id,
                            $translation->translatable_column,
                            $translation->locale,
                        ));

                    foreach ($locales as $locale) {
                        $payload = [];

                        foreach ($models as $model) {
                            foreach ($columns as $column) {
                                $source = $model->getRawOriginal($column);

                                if (! is_string($source) || blank(trim($source))) {
                                    continue;
                                }

                                $translationKey = $this->storedTranslationKey(
                                    $model->getMorphClass(),
                                    (string) $model->getKey(),
                                    $column,
                                    $locale,
                                );
                                $isCurrent = $existingTranslations
                                    ->get($translationKey, collect())
                                    ->contains(fn (Translation $translation): bool => $translation->content_original === $source
                                        && filled($translation->content)
                                    );

                                if ($isCurrent) {
                                    continue;
                                }

                                $payload[] = [
                                    'translatable_type' => $model->getMorphClass(),
                                    'translatable_id' => (string) $model->getKey(),
                                    'translatable_column' => $column,
                                    'content_original' => $source,
                                ];
                            }
                        }

                        if (empty($payload)) {
                            continue;
                        }

                        $prompt = 'Detect the language of each content_original value and translate it to '.$locale.'. If it is already in the target language, preserve it. '
                            .'Return only valid JSON with the exact shape {"items":[{"translatable_type":"...","translatable_id":"...","translatable_column":"...","content":"..."}]}. '
                            .'Copy every type, id and column exactly. Preserve HTML, Markdown, placeholders, URLs and code. '
                            .'When content_original is JSON, preserve its structure, keys, field ids, input types, validation rules and machine values; translate only human-readable labels, titles, descriptions, placeholders, help text and option labels. Payload: '
                            .json_encode(['items' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $translated = $this->runTranslationPrompt($prompt);
                        $allowedItems = collect($payload)->keyBy(fn (array $item): string => $this->dynamicItemKey($item));

                        foreach (data_get($translated, 'items', []) as $item) {
                            if (! is_array($item)) {
                                continue;
                            }

                            $sourceItem = $allowedItems->get($this->dynamicItemKey($item));
                            $content = $this->translatedDynamicContent($item['content'] ?? null);

                            if (! is_array($sourceItem) || $content === null) {
                                continue;
                            }

                            Translation::query()->updateOrCreate(
                                [
                                    'translatable_type' => $sourceItem['translatable_type'],
                                    'translatable_id' => $sourceItem['translatable_id'],
                                    'translatable_column' => $sourceItem['translatable_column'],
                                    'locale' => $locale,
                                ],
                                [
                                    'content_original' => $sourceItem['content_original'],
                                    'content' => $content,
                                ]
                            );
                            $translatedCount++;
                        }
                    }
                });
        }

        $this->info("Dynamic content translated: {$translatedCount} fields.");
    }

    /** @param array<string, mixed> $item */
    private function dynamicItemKey(array $item): string
    {
        return implode('|', [
            (string) ($item['translatable_type'] ?? ''),
            (string) ($item['translatable_id'] ?? ''),
            (string) ($item['translatable_column'] ?? ''),
        ]);
    }

    private function storedTranslationKey(string $type, string $id, string $column, string $locale): string
    {
        return implode('|', [$type, $id, $column, $locale]);
    }

    private function translatedDynamicContent(mixed $content): ?string
    {
        if (is_string($content) && filled(trim($content))) {
            return $content;
        }

        if (is_array($content) || is_object($content)) {
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
        }

        return null;
    }

    private function hasSamePlaceholders(string $source, string $translated): bool
    {
        preg_match_all('/\{[^{}]+\}/', $source, $sourceMatches);
        preg_match_all('/\{[^{}]+\}/', $translated, $translatedMatches);

        $sourcePlaceholders = $sourceMatches[0] ?? [];
        $translatedPlaceholders = $translatedMatches[0] ?? [];
        sort($sourcePlaceholders);
        sort($translatedPlaceholders);

        return $sourcePlaceholders === $translatedPlaceholders;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTranslationSourceState(): array
    {
        $filePath = storage_path(self::SOURCE_STATE_FILE);

        if (! file_exists($filePath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($filePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function saveTranslationSourceState(): void
    {
        $filePath = storage_path(self::SOURCE_STATE_FILE);

        if (! is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0775, true);
        }

        file_put_contents(
            $filePath,
            json_encode($this->translationSourceState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    private function backendLocaleFilesExist(string $locale): bool
    {
        foreach (array_keys($this->backendContentBase) as $filename) {
            if (! file_exists(base_path("lang/{$locale}/{$filename}"))) {
                return false;
            }
        }

        return true;
    }

    private function sourceHash(mixed $source): string
    {
        $encodedSource = is_string($source)
            ? $source
            : json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', (string) $encodedSource);
    }

    /**
     * @return array<string, mixed>
     */
    private function runTranslationPrompt(string $systemPrompt): array
    {
        $lastContent = '';

        for ($attempt = 1; $attempt <= self::TRANSLATION_ATTEMPTS; $attempt++) {
            $response = app(AiProvider::class)->system($systemPrompt)->run();
            $rawContent = data_get($response, 'content', '');
            $decoded = $this->decodeTranslationResponse($rawContent);

            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }

            $lastContent = is_string($rawContent)
                ? $rawContent
                : (json_encode($rawContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            Log::warning('Translation provider returned invalid JSON.', [
                'attempt' => $attempt,
                'translatedContent' => Str::limit($lastContent, 4000),
            ]);
        }

        Log::error('Translation command failed after retrying invalid translated content.', [
            'translatedContent' => Str::limit($lastContent, 4000),
        ]);

        throw new \RuntimeException('Translation command failed. Invalid translated content received after retrying.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeTranslationResponse(mixed $rawContent): ?array
    {
        if (is_array($rawContent)) {
            return $rawContent;
        }

        if (is_object($rawContent)) {
            $rawContent = json_encode($rawContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (! is_string($rawContent) || blank($rawContent)) {
            return null;
        }

        $content = trim($rawContent);
        $decoded = $this->decodeJsonArray($content);

        if ($decoded !== null) {
            return $decoded;
        }

        return $this->extractFirstJsonArray($content);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonArray(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractFirstJsonArray(string $content): ?array
    {
        $length = strlen($content);

        for ($start = 0; $start < $length; $start++) {
            if (! in_array($content[$start], ['{', '['], true)) {
                continue;
            }

            $stack = [];
            $insideString = false;
            $escaped = false;

            for ($position = $start; $position < $length; $position++) {
                $character = $content[$position];

                if ($insideString) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($character === '\\') {
                        $escaped = true;
                    } elseif ($character === '"') {
                        $insideString = false;
                    }

                    continue;
                }

                if ($character === '"') {
                    $insideString = true;

                    continue;
                }

                if ($character === '{') {
                    $stack[] = '}';
                } elseif ($character === '[') {
                    $stack[] = ']';
                } elseif ($character === '}' || $character === ']') {
                    if (array_pop($stack) !== $character) {
                        break;
                    }

                    if ($stack === []) {
                        $decoded = $this->decodeJsonArray(substr($content, $start, $position - $start + 1));

                        if ($decoded !== null) {
                            return $decoded;
                        }

                        break;
                    }
                }
            }
        }

        return null;
    }
}
