<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use HasUlids;
    
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'translatable_column',
        'locale',
        'content_original',
        'content',
    ];

    protected $table = 'translations';

    public static function new($model, $column, $originalContent)
    {

        // First check if the translation exists for available locales, if not, create a new translation job for the missing locales
        // $existingTranslation = Translation::where('translatable_type', get_class($model))
        //     ->where('translatable_id', $model->id)
        //     ->where('translatable_column', $column)
        //     ->where('content_original', $originalContent)
        //     ->where('content', '!=', '')
        //     ->whereIn('locale', config('app.locales') ?? ['en']);
        // if ($existingTranslation->count() == 0) {
            Translation::updateOrCreate([
                'translatable_type' => get_class($model),
                'translatable_id' => $model->id,
                'translatable_column' => trim($column),
                'locale' => 'en',
                'content' => '',
                'content_original' => trim($originalContent),
            ]);
            dispatch_sync(new \App\Jobs\TranslateJob(Translation::latest()->first()->id));
            $existingTranslation = Translation::where('translatable_type', get_class($model))
                ->where('translatable_id', $model->id)
                ->where('translatable_column', trim($column))
                ->where('content_original', trim($originalContent))
                ->whereIn('locale', config('app.locales') ?? ['en'])
                ->get();
        // }

        return $existingTranslation->pluck('content', 'locale')->toArray();
    }
}
