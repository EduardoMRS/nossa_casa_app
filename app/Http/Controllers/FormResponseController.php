<?php

namespace App\Http\Controllers;

use App\Models\EventUser;
use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormResponseController extends Controller
{
    public function store(Request $request, Form $form): JsonResponse
    {
        $this->ensurePublicChurchResource($form->church_id);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $answers = $validated['answers'];
        $fields = collect($form->schema);

        if (isset($form->schema['fields']) && is_array($form->schema['fields'])) {
            $fields = collect($form->schema['fields']);
        }

        $errors = [];

        $fields->each(function (mixed $field) use (&$errors, $answers): void {
            if (! is_array($field)) {
                return;
            }

            $key = $field['name'] ?? $field['key'] ?? $field['id'] ?? null;
            $required = (bool) ($field['required'] ?? false);

            if (! $key || ! $required) {
                return;
            }

            $value = $answers[$key] ?? null;

            if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                $errors["answers.{$key}"] = __('form.field_required', ['field' => $key]);
            }
        });

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $response = FormResponse::updateOrCreate(
            [
                'form_id' => $form->id,
                'user_id' => $request->user()->id,
            ],
            [
                'answers' => $answers,
            ]
        );

        $relatedEvent = $form->events()->first();

        if ($relatedEvent) {
            EventUser::query()->firstOrCreate(
                [
                    'event_id' => $relatedEvent->id,
                    'user_id' => $request->user()->id,
                ],
                [
                    'status' => 'pending',
                ]
            );
        }

        return response()->json([
            'message' => __('common.notifications.form_response_saved'),
            'data' => $response,
        ], $response->wasRecentlyCreated ? 201 : 200);
    }
}
