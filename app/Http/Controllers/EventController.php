<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('church')->orderBy('start_time', 'asc')->paginate(15);
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:events',
            'tags'        => 'nullable|array',
            'tags.*'      => 'string',
            'description' => 'nullable|string',
            'start_time'  => 'required|date',
            'end_time'    => 'nullable|date|after_or_equal:start_time',
            'cover_path'  => 'nullable|string|max:255',
            'church_id'   => 'required|string|exists:churches,id',
            'author_id'   => 'required|string|exists:users,id',
        ]);

        $event = Event::create($validated);

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

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'slug'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('events')->ignore($event->id)],
            'tags'        => 'nullable|array',
            'tags.*'      => 'string',
            'description' => 'nullable|string',
            'start_time'  => 'sometimes|required|date',
            'end_time'    => 'nullable|date|after_or_equal:start_time',
            'cover_path'  => 'nullable|string|max:255',
            'church_id'   => 'sometimes|required|string|exists:churches,id',
            'author_id'   => 'sometimes|required|string|exists:users,id',
        ]);

        $event->update($validated);

        return response()->json($event);
    }

    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);
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
            'status' => 'checked_in',
        ]);

        return response()->json(['message' => 'Check-in successful.'], 200);
    }
}
