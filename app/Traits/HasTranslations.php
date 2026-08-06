<?php

namespace App\Traits;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasTranslations
{
    /** @var array<string, array<string, string>> */
    private array $translationsByLocale = [];

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * @return array<string, string>
     */
    public function getTranslationsAttribute(): array
    {
        return $this->translationsForLocale();
    }

    /**
     * @return array<string, string>
     */
    public function translationsForLocale(?string $locale = null): array
    {
        $requestedLocale = $locale ?? app()->getLocale();
        $normalizedLocale = $this->normalizeTranslationLocale($requestedLocale);
        $cacheKey = mb_strtolower(str_replace('_', '-', trim($requestedLocale)));

        if (array_key_exists($cacheKey, $this->translationsByLocale)) {
            return $this->translationsByLocale[$cacheKey];
        }

        return $this->translationsByLocale[$cacheKey] = $this->translations()
            ->oldest('updated_at')
            ->get(['locale', 'content', 'content_original', 'translatable_column'])
            ->filter(fn (Translation $translation): bool => $this->normalizeTranslationLocale($translation->locale) === $normalizedLocale
                && filled($translation->content)
                && $translation->content_original === (string) $this->getRawOriginal($translation->translatable_column)
            )
            ->sortBy(fn (Translation $translation): int => mb_strtolower($translation->locale) === mb_strtolower($requestedLocale) ? 1 : 0
            )
            ->mapWithKeys(fn (Translation $translation): array => [
                $translation->translatable_column => $translation->content,
            ])
            ->all();
    }

    /**
     * Replace translated attributes only on this in-memory model instance.
     *
     * @param  array<int, string>  $relations
     */
    public function localize(?string $locale = null, array $relations = []): static
    {
        foreach ($this->translationsForLocale($locale) as $column => $content) {
            if (! array_key_exists($column, $this->getAttributes())) {
                continue;
            }

            $this->setAttribute($column, $this->translatedAttributeValue($column, $content));
        }

        foreach ($relations as $relation) {
            if (! $this->relationLoaded($relation)) {
                continue;
            }

            $this->localizeRelatedValue($this->getRelation($relation), $locale);
        }

        return $this;
    }

    private function translatedAttributeValue(string $column, string $content): mixed
    {
        if (! $this->hasCast($column, ['array', 'json', 'object', 'collection'])) {
            return $content;
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $content;
        }

        if ($this->hasCast($column, 'object')) {
            return (object) $decoded;
        }

        if ($this->hasCast($column, 'collection')) {
            return collect($decoded);
        }

        return $decoded;
    }

    private function localizeRelatedValue(mixed $related, ?string $locale): void
    {
        if ($related instanceof Model && method_exists($related, 'localize')) {
            $related->localize($locale);

            return;
        }

        if ($related instanceof Collection) {
            $related->each(function (mixed $model) use ($locale): void {
                if ($model instanceof Model && method_exists($model, 'localize')) {
                    $model->localize($locale);
                }
            });
        }
    }

    private function normalizeTranslationLocale(string $locale): string
    {
        return mb_strtolower(explode('-', str_replace('_', '-', trim($locale)))[0]);
    }
}
