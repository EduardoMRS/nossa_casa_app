<?php

namespace App\Jobs;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Translation;
class TranslateJob implements ShouldQueue
{
    use Queueable;

    protected $translation_id;
    protected $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct($translation_id)
    {
        $this->translation_id = $translation_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug('Starting translation job for translation ID: ' . $this->translation_id);
        $translation = Translation::find($this->translation_id);
        if(!$translation) return;
        $systemPrompt = 'You are a professional translator.
        Translation the following text to: "' . join(',', config('app.locales')) . '"
        Do not translate any text inside brackets [] labels of values and return in json winthout any other text or explanation. The output should be in the following format:
            [{"translatable_type":"type", "translatable_id":"id", "translatable_column":"text translated", "content":"translated text", "original_content":"original text", "locale":"locale code"}]
        Example of the output:
            [
                {"translatable_type":"App\\Models\\Post", "translatable_id":"1", "translatable_column":"title", "content":"Inauguração do Templo Central", "original_content":"Inauguration of the Central Temple", "locale":"pt-BR"},
                {"translatable_type":"App\\Models\\Post", "translatable_id":"1", "translatable_column":"title", "content":"Inauguration of the Central Temple", "original_content":"Inauguration of the Central Temple", "locale":"en"},
                {"translatable_type":"App\\Models\\Post", "translatable_id":"1", "translatable_column":"description", "content":"É com grande alegria que convidamos a todos.", "original_content":"It is with great joy that we invite everyone...", "locale":"en"},
                {"translatable_type":"App\\Models\\Post", "translatable_id":"1", "translatable_column":"description", "content":"It is with great joy that we invite everyone...", "original_content":"It is with great joy that we invite everyone...", "locale":"en"},
            ]
        Content to be translated:
            [{"translatable_type":"' . $translation->translatable_type . '", "translatable_id":"' . $translation->translatable_id . '", "translatable_column":"' . $translation->translatable_column . '", "content":"", "original_content":"' . $translation->content_original . '"} ]
        ';
        $aiProvider = new \App\Services\AiProvider();
        $translatedContent = $aiProvider->system($systemPrompt)->run();
        $translatedContent = str_replace('\"', '"', data_get($translatedContent, 'content', ''));
        Log::debug('Translated content: ', [$translatedContent]);
        $translatedContent = \Str::replaceFirst('\\n', '', $translatedContent);
        $translatedContent = \Str::replaceLast('\\n', '', $translatedContent);
        $translatedContent = json_decode(trim($translatedContent), true);
        if(!is_array($translatedContent) || empty($translatedContent)) {
            Log::error('Translation job failed for translation ID: ' . $this->translation_id . '. Invalid translated content received.', ['translatedContent' => $translatedContent]);
            throw new \Exception('Translation job failed for translation ID: ' . $this->translation_id . '. Invalid translated content received.');
        }
        foreach ($translatedContent as $item) {
            Translation::updateOrCreate([
                'translatable_type' => $item['translatable_type'],
                'translatable_id' => $item['translatable_id'],
                'translatable_column' => trim($item['translatable_column']),
                'locale' => $item['locale'],
            ], [
                'content' => trim($item['content']),
                'content_original' => trim($item['original_content']),
            ]);
        }
        Log::debug('Translation job completed for translation ID: ' . $this->translation_id);
    }
}
