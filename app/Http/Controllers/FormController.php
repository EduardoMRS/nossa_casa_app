<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $churchId = $request->user()->church?->id;
        $isAdministrator = in_array($request->user()->role->value, ['admin', 'superadmin', 'system'], true);

        abort_unless($churchId || $isAdministrator, 422, 'A church membership is required to list forms.');

        return response()->json(Form::query()
            ->when(! $isAdministrator, fn ($query) => $query->where('church_id', $churchId))
            ->with(['events', 'responses'])
            ->paginate(15));
    }

    public function show(Request $request, Form $form)
    {
        $this->ensureChurchAccess($request, $form->church_id);

        return response()->json($form->load(['events', 'responses']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'schema' => ['required', 'array'],
        ]);
        $church = $request->user()->church;

        abort_unless($church && $church->exists(), 422, __('church.membership_form_create_required'));

        $form = $church->forms()->create($validated);

        return response()->json($form, 201);
    }

    public function update(Request $request, Form $form)
    {
        $this->ensureChurchAccess($request, $form->church_id);
        $form->update($request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'schema' => ['sometimes', 'required', 'array'],
        ]));

        return response()->json($form);
    }

    public function destroy(Request $request, Form $form)
    {
        $this->ensureChurchAccess($request, $form->church_id);
        $form->delete();

        return response()->noContent();
    }
}
