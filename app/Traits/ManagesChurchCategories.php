<?php

namespace App\Traits;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ManagesChurchCategories
{
    protected function availableChurchCategories(?string $churchId, string $type): Collection
    {
        if (! $churchId) {
            return collect();
        }

        return Category::query()
            ->where('church_id', $churchId)
            ->where('type', $type)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type'])
            ->each->makeHidden('translations');
    }

    protected function syncChurchCategories(Request $request, string $type, ?string $churchId = null): array
    {
        $churchId ??= $request->user()?->church?->id;
        $categoryIds = collect($request->input('category_ids', []))
            ->filter()
            ->map(fn ($categoryId) => (string) $categoryId)
            ->values();

        if (! $churchId || $categoryIds->isEmpty()) {
            return [];
        }

        return Category::query()
            ->where('church_id', $churchId)
            ->where('type', $type)
            ->whereIn('id', $categoryIds->all())
            ->pluck('id')
            ->all();
    }
}
