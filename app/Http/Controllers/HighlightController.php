<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Event;
use App\Models\Highlight;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;

class HighlightController extends Controller
{
    public function updateChurchHighlights(Request $request, string $churchId)
    {
        $church = Church::findOrFail($churchId);
        $this->ensureChurchAccess($request, $church->id);

        $validated = $request->validate([
            'highlights' => 'required|array',
            'highlights.*.id' => 'required|string',
            'highlights.*.type' => 'required|string|in:post,event,media',
            'highlights.*.order' => 'required|integer',
        ]);

        // Remove os destaques antigos para substituir pela nova ordem
        Highlight::where('church_id', $church->id)->delete();

        $highlights = collect($validated['highlights'])->map(function ($item) use ($church) {
            $highlightableClass = match ($item['type']) {
                'post' => Post::class,
                'event' => Event::class,
                'media' => Media::class,
            };

            $highlightable = $highlightableClass::query()->findOrFail($item['id']);
            abort_unless($highlightable->church_id === $church->id, 422, __('church.highlights_must_match'));

            return Highlight::create([
                'highlightable_id' => $item['id'],
                'highlightable_type' => $highlightableClass,
                'church_id' => $church->id,
                'order' => $item['order'],
            ]);
        });

        return response()->json($highlights, 200);
    }
}
