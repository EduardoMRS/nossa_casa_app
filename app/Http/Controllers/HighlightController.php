<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Highlight;
use Illuminate\Http\Request;

class HighlightController extends Controller
{
    public function updateChurchHighlights(Request $request, string $churchId)
    {
        $church = Church::findOrFail($churchId);

        $validated = $request->validate([
            'highlights' => 'required|array',
            'highlights.*.id' => 'required|string',
            'highlights.*.type' => 'required|string|in:post,event,media',
            'highlights.*.order' => 'required|integer',
        ]);

        // Remove os destaques antigos para substituir pela nova ordem
        Highlight::where('church_id', $church->id)->delete();

        $highlights = collect($validated['highlights'])->map(function ($item) use ($church) {
            $highlightableClass = match($item['type']) {
                'post' => \App\Models\Post::class,
                'event' => \App\Models\Event::class,
                'media' => \App\Models\Media::class,
            };

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
