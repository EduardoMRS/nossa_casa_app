<?php

namespace App\Http\Controllers;

use App\Http\Requests\Form\StoreFormRequest;
use App\Http\Requests\Form\UpdateFormRequest;
use App\Models\Event;
use App\Models\Form;
use App\Models\Post;
use App\Traits\ManagesChurchCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    use ManagesChurchCategories;

    public function index(Request $request): JsonResponse
    {
        $churchId = $request->user()->church?->id;

        abort_unless($churchId, 422, __('church.membership_form_list_required'));

        return response()->json(Form::query()
            ->where('church_id', $churchId)
            ->with(['events:id,title', 'posts:id,title'])
            ->withCount('responses')
            ->latest()
            ->paginate(15)
            ->through(fn (Form $form): Form => $form->localize(relations: ['events', 'posts'])));
    }

    public function show(Request $request, Form $form): JsonResponse
    {
        $this->ensureChurchAccess($request, $form->church_id);

        $form->load(['events:id,title', 'posts:id,title', 'responses.user:id,first_name,last_name'])
            ->localize(relations: ['events', 'posts']);

        return response()->json($form);
    }

    public function store(StoreFormRequest $request): JsonResponse
    {
        $church = $request->user()->church;

        abort_unless($church?->exists, 422, __('church.membership_form_create_required'));

        $validated = $request->validated();
        $form = $church->forms()->create($request->safe()->only(['title', 'description', 'schema']));

        $this->syncRelations($form, $validated, $church->id);

        return response()->json($form->load(['events:id,title', 'posts:id,title']), 201);
    }

    public function update(UpdateFormRequest $request, Form $form): JsonResponse
    {
        $this->ensureChurchAccess($request, $form->church_id);

        $validated = $request->validated();
        $form->update($request->safe()->only(['title', 'description', 'schema']));
        $this->syncRelations($form, $validated, $form->church_id);

        return response()->json($form->load(['events:id,title', 'posts:id,title']));
    }

    public function destroy(Request $request, Form $form): JsonResponse
    {
        $this->ensureChurchAccess($request, $form->church_id);
        $form->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRelations(Form $form, array $validated, string $churchId): void
    {
        if (array_key_exists('category_ids', $validated)) {
            $form->categories()->sync($this->syncChurchCategories(request(), 'form', $churchId));
        }

        if (array_key_exists('event_ids', $validated)) {
            $eventIds = Event::query()->where('church_id', $churchId)->whereIn('id', $validated['event_ids'])->pluck('id');
            $form->events()->sync($eventIds);
        }

        if (array_key_exists('post_ids', $validated)) {
            $postIds = Post::query()->where('church_id', $churchId)->whereIn('id', $validated['post_ids'])->pluck('id');
            $form->posts()->sync($postIds);
        }
    }
}
