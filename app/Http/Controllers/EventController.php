<?php // app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\UploadsMedia;

class EventController extends Controller
{
    use UploadsMedia;

    public function index()
    {
        $events = Event::with('church')->orderBy('start_time', 'asc')->paginate(15);

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:events',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'cover_path' => 'nullable'
        ]);

        $data['church_id'] = $user->church->id ?? null;
        $data['author_id'] = $user->id;
        abort_unless($data['church_id'], 422, 'A church membership is required to create events.');

        if (array_key_exists('cover_path', $data)) {
            $file = $request->file('cover_path') ?? $request->input('cover_path');
            $data['cover_path'] = $this->handleMediaUpload($file, "church/{$data['church_id']}/events/covers");
        }

        $event = Event::create($data);

        return response()->json($event, 201);
    }

    public function show(string $slug)
    {
        $event = Event::with(['church', 'categories', 'address', 'confirmations'])->where('slug', $slug)->firstOrFail();

        return response()->json($event);
    }

    public function update(Request $request, string $id)
    {
        $event = Event::findOrFail($id);
        $this->ensureChurchAccess($request, $event->church_id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('events')->ignore($event->id)],
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'description' => 'nullable|string',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'cover_path' => 'sometimes|nullable', 
        ]);

        if (array_key_exists('cover_path', $validated)) {
            $file = $request->file('cover_path') ?? $request->input('cover_path');
            $validated['cover_path'] = $this->handleMediaUpload($file, "church/{$event->church_id}/events/covers", $event->cover_path);
        }

        $event->update($validated);

        return response()->json($event);
    }

    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);
        $this->ensureChurchAccess(request(), $event->church_id);
        
        // Opcional: deletar o arquivo cover ao excluir o evento
        // if ($event->cover_path) \Illuminate\Support\Facades\Storage::disk('public')->delete($event->cover_path);
        
        $event->delete();

        return response()->json(null, 204);
    }

     public function checkin(Request $request, string $id)
    {
        $event = Event::findOrFail($id);
        $user = $request->user();

        // Check if the user has already checked in
        if ($event->confirmations()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User has already checked in.'], 400);
        }

        // Create a new confirmation for the user
        $event->confirmations()->create([
            'user_id' => $user->id,
            'check_in_at' => now(),
        ]);

        return response()->json(['message' => 'Check-in successful.'], 200);
    }
}
