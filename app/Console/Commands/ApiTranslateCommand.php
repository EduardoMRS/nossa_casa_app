<?php

namespace App\Console\Commands;

use App\Services\AiProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Signature('lang:translate')]
#[Description('Translate API returns')]
class ApiTranslateCommand extends Command
{
    protected array $backendContentBase = [];

    protected array $frontendContentBase = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $locales = config('app.locales') ?? ['en', 'pt'];
        $locales = array_values(array_filter($locales, fn ($locale) => $locale !== 'en'));

        if (empty($locales)) {
            $this->warn('No target locales found. Set APP_LOCALES (e.g. en,pt).');

            return self::SUCCESS;
        }

        $this->backendContentBase = $this->loadBackendEnglishFiles();
        $this->frontendContentBase = $this->loadFrontendEnglishJson();

        $this->translateBackendText($locales);
        $this->translateFrontendJson($locales);

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
        if (empty($this->backendContentBase)) {
            $this->warn('No backend lang/en/*.php files were found.');

            return;
        }

        Log::debug('Starting backend translation for locales: '.implode(',', $locales));

        $systemPrompt = 'You are a professional translator. Translate this Laravel PHP language payload from English to these locales: '
            .implode(', ', $locales)
            .'. Return only valid JSON with this exact shape: {"locale": {"filename.php": {...translated keys...}}}. '
            .'Keep all keys and placeholders exactly as provided. Payload: '
            .json_encode(['en' => $this->backendContentBase], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $translatedContent = $this->runTranslationPrompt($systemPrompt);

        unset($translatedContent['en']);

        foreach ($translatedContent as $lang => $files) {
            if (! in_array($lang, $locales, true) || ! is_array($files)) {
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
        }

        $this->info('Backend PHP lang files translated.');
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function translateFrontendJson(array $locales): void
    {
        if (empty($this->frontendContentBase)) {
            $this->warn('No frontend resources/js/locales/en.json was found.');

            return;
        }

        Log::debug('Starting frontend JSON translation for locales: '.implode(',', $locales));

        $systemPrompt = 'You are a professional translator. Translate this frontend i18n JSON payload from English to these locales: '
            .implode(', ', $locales)
            .'. Return only valid JSON with this exact shape: {"locale": { ...translated keys... }}. '
            .'Keep all keys and placeholders exactly as provided. Payload: '
            .json_encode(['en' => $this->frontendContentBase], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $translatedContent = $this->runTranslationPrompt($systemPrompt);

        unset($translatedContent['en']);

        foreach ($translatedContent as $lang => $translations) {
            if (! in_array($lang, $locales, true) || ! is_array($translations)) {
                continue;
            }

            $filePath = base_path("resources/js/locales/{$lang}.json");
            if (! is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0775, true);
            }

            file_put_contents(
                $filePath,
                json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );
        }

        $this->info('Frontend JSON locale files translated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function runTranslationPrompt(string $systemPrompt): array
    {
        $aiProvider = new AiProvider;
        $response = $aiProvider->system($systemPrompt)->run();
        $content = (string) data_get($response, 'content', '');

        $content = str_replace('```json', '', $content);
        $content = str_replace('```', '', $content);
        $content = str_replace('\"', '"', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        if (! is_array($decoded) || empty($decoded)) {
            Log::error('Translation command failed. Invalid translated content received.', ['translatedContent' => $content]);
            throw new \RuntimeException('Translation command failed. Invalid translated content received.');
        }

        return $decoded;
    }
}
