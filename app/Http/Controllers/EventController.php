<?php

// app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\User;
use App\Traits\ManagesChurchCategories;
use App\Traits\UploadsMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

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
        $responsibleIds = Arr::pull($data, 'responsible_ids', []);
        $address = Arr::pull($data, 'address', []);

        $data['church_id'] = $user->church->id ?? null;
        $data['author_id'] = $user->id;
        abort_unless($data['church_id'], 422, __('church.membership_event_create_required'));

        if (array_key_exists('cover_path', $data)) {
            $file = $request->file('cover_path') ?? $request->input('cover_path');
            $data['cover_path'] = $this->handleMediaUpload($file, "church/{$data['church_id']}/events/covers");
        }

        $event = Event::create($data);
        $event->categories()->sync($this->syncChurchCategories($request, CategoryType::EVENT->value, $data['church_id']));
        $this->syncRegistrationForm($event, $request->input('form_id'), $data['church_id']);
        $this->syncResponsibleUsers($event, $responsibleIds, $data['church_id']);
        $this->syncAddress($event, $address);

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.event_created')]);

            return redirect()->route('admin.events.index');
        }

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
        $responsibleIds = Arr::pull($validated, 'responsible_ids', null);
        $address = Arr::pull($validated, 'address', null);

        if (array_key_exists('cover_path', $validated)) {
            $file = $request->file('cover_path') ?? $request->input('cover_path');
            $validated['cover_path'] = $this->handleMediaUpload($file, "church/{$event->church_id}/events/covers", $event->getRawOriginal('cover_path'));
        }

        $event->update($validated);
        if ($request->has('category_ids')) {
            $event->categories()->sync($this->syncChurchCategories($request, CategoryType::EVENT->value, $event->church_id));
        }
        if ($request->has('form_id')) {
            $this->syncRegistrationForm($event, $request->input('form_id'), $event->church_id);
        }
        if ($responsibleIds !== null) {
            $this->syncResponsibleUsers($event, $responsibleIds, $event->church_id);
        }
        if ($address !== null) {
            $this->syncAddress($event, $address);
        }

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.event_updated')]);

            return redirect()->route('admin.events.index');
        }

        return response()->json($event);
    }

    public function destroy(Request $request, string $id)
    {
        $event = Event::findOrFail($id);
        $this->ensureChurchAccess($request, $event->church_id);

        if ($event->getRawOriginal('cover_path')) {
            Storage::disk((string) config('media.disk'))->delete($event->getRawOriginal('cover_path'));
        }

        $event->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.event_deleted')]);

            return redirect()->route('admin.events.index');
        }

        return response()->json(null, 204);
    }

    public function checkin(Request $request, string $id)
    {
        $event = Event::findOrFail($id);
        $user = $request->user();
        $this->ensurePublicChurchResource($event->church_id);

        abort_unless(
            $event->users()->whereKey($user->id)->exists(),
            422,
            __('checkin.event_registration_required'),
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

    private function syncRegistrationForm(Event $event, mixed $formId, string $churchId): void
    {
        if ($formId === null || $formId === '') {
            $event->forms()->detach();

            return;
        }

        abort_unless(
            Form::query()->whereKey($formId)->where('church_id', $churchId)->exists(),
            422,
            __('church.selected_form_must_match'),
        );

        $event->forms()->sync([$formId]);
    }

    /** @param array<int, string> $responsibleIds */
    private function syncResponsibleUsers(Event $event, array $responsibleIds, string $churchId): void
    {
        $validIds = User::query()
            ->whereIn('id', $responsibleIds)
            ->whereHas('profile', fn ($query) => $query->where('church_id', $churchId))
            ->pluck('id');

        $event->responsibleUsers()->sync($validIds);
    }

    /** @param array<string, mixed> $address */
    private function syncAddress(Event $event, array $address): void
    {
        $address = collect($address)
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();

        if ($address === []) {
            $event->address()->delete();

            return;
        }

        $event->address()->updateOrCreate([], [
            'country' => $address['country'] ?? 'Brasil',
            'state' => $address['state'] ?? '',
            'city' => $address['city'] ?? '',
            'neighborhood' => $address['neighborhood'] ?? null,
            'street' => $address['street'] ?? '',
            'number' => $address['number'] ?? null,
            'complement' => $address['complement'] ?? null,
            'zipcode' => $address['zipcode'] ?? '',
        ]);
    }
}
