<?php

// app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Event;
use App\Traits\ManagesChurchCategories;
use App\Traits\UploadsMedia;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ManagesChurchCategories;
    use UploadsMedia;

    public function index()
    {
        $events = Event::with('church')
            ->orderBy('start_time', 'asc')
            ->paginate(15)
            ->through(fn (Event $event): Event => $event->localize(relations: ['church']));

        return response()->json($events);
    }

    public function store(StoreEventRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        $data['church_id'] = $user->church->id ?? null;
        $data['author_id'] = $user->id;
        abort_unless($data['church_id'], 422, 'A church membership is required to create events.');

        if (array_key_exists('cover_path', $data)) {
            $file = $request->file('cover_path') ?? $request->input('cover_path');
            $data['cover_path'] = $this->handleMediaUpload($file, "church/{$data['church_id']}/events/covers");
        }

        $event = Event::create($data);
        $event->categories()->sync($this->syncChurchCategories($request, CategoryType::EVENT->value, $data['church_id']));

        return response()->json($event, 201);
    }

    public function show(string $slug)
    {
        $event = Event::with(['church', 'categories', 'address', 'confirmations'])->where('slug', $slug)->firstOrFail();
        $event->localize(relations: ['church', 'categories']);

        return response()->json($event);
    }

    public function update(UpdateEventRequest $request, string $id)
    {
        $event = Event::findOrFail($id);
        $this->ensureChurchAccess($request, $event->church_id);

        $validated = $request->validated();

        if (array_key_exists('cover_path', $validated)) {
            $file = $request->file('cover_path') ?? $request->input('cover_path');
            $validated['cover_path'] = $this->handleMediaUpload($file, "church/{$event->church_id}/events/covers", $event->cover_path);
        }

        $event->update($validated);
        if ($request->has('category_ids')) {
            $event->categories()->sync($this->syncChurchCategories($request, CategoryType::EVENT->value, $event->church_id));
        }

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
        $this->ensureChurchAccess($request, $event->church_id);

        abort_unless(
            $event->users()->whereKey($user->id)->exists(),
            422,
            'Event registration is required before check-in.'
        );

        // Check if the user has already checked in
        if ($event->confirmations()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => __('checkin.already_checked_in')], 400);
        }

        // Create a new confirmation for the user
        $event->confirmations()->create([
            'user_id' => $user->id,
            'check_in_at' => now(),
        ]);

        return response()->json(['message' => __('checkin.checkin_success')], 200);
    }
}
