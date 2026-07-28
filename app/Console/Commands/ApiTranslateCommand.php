<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('lang:translate')]
#[Description('Translate API returns')]
class ApiTranslateCommand extends Command
{
    protected $contentBase = '';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = base_path('lang/en/*.php'); // Arquivos php que retornam arrays de traduções
        $filesEn = glob($path);
        $contentEn = [];
        foreach ($filesEn as $file) {
            $translations = include $file;
            $contentEn[basename($file)] = $translations;
        }
        $this->contentBase = $contentEn;
        $this->translateText();
    }

    private function translateText()
    {
        $locales = config('app.locales') ?? ['en', 'pt'];
        $locales = array_filter($locales, fn($locale) => $locale !== 'en'); // Remove 'en' from the locales to translate
        Log::debug('Starting translation available languages: ' . join(',', $locales));
        $systemPrompt = 'You are a professional translator.
        Translation the following Content to "' . join(',', $locales) . '" of laravel translation files. The output should be in the following format only:
            {"lang": 
                {
                    "filename.php": {
                        "key1": "translated text 1",
                        "key2": "translated text 2",
                        "key3": "translated text 3"
                    }
                }
            }
        Example of the output: {"pt": {"pagination.php": {"previous": "&laquo; Anterior","next": "Seguinte &raquo;"}}}
        Content to be translated: {"en": ' . json_encode($this->contentBase) . '}
        ';

        $aiProvider = new \App\Services\AiProvider();
        $translatedContent = $aiProvider->system($systemPrompt)->run();
        $translatedContent = str_replace('\"', '"', data_get($translatedContent, 'content', ''));
        $translatedContent = str_replace('```json', '', $translatedContent);
        $translatedContent = str_replace('```', '', $translatedContent);
        
        Log::debug('Translated content: ', [$translatedContent]);
        $translatedContent = \Str::replaceFirst('\\n', '', $translatedContent);
        $translatedContent = \Str::replaceLast('\\n', '', $translatedContent);
        $translatedContent = json_decode(trim($translatedContent), true);
        if(!is_array($translatedContent) || empty($translatedContent)) {
            Log::error('Translation command failed. Invalid translated content received.', ['translatedContent' => $translatedContent]);
            throw new \Exception('Translation command failed. Invalid translated content received.');
        }
        unset($translatedContent['en']); // Remove the English content from the translated content
        foreach ($translatedContent as $lang => $files) {
            foreach ($files as $filename => $translations) {
                $filePath = base_path("lang/{$lang}/{$filename}");
                if (!is_dir(dirname($filePath))) {
                    mkdir(dirname($filePath), 0775, true);
                }
                $content = "<?php\n\nreturn " . var_export($translations, true);
                $content = preg_replace('/array\s*\(/', '[', $content);
                $content = preg_replace('/\)\s*;?\s*$/', '];', $content);
                // Corrige '),' para '],'
                $content = str_replace('),', '],', $content);
                $content = preg_replace('/;;+/', ';', $content);
                file_put_contents($filePath, $content . "\n");
            }
        }
        Log::debug('Translation command completed successfully.');
    }
}
