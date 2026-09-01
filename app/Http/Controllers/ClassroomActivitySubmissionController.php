<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassroomActivitySubmissionController extends Controller
{
    public function store(Request $request, Classroom $classroom, ClassroomActivity $activity): JsonResponse
    {
        abort_unless($activity->classroom_id === $classroom->id, 404);
        $this->ensurePublicChurchResource($classroom->church_id);
        abort_unless($request->user()->can('submitActivity', $classroom), 403);
        abort_unless($activity->is_published && (! $activity->published_at || $activity->published_at->isPast()), 404);

        if ($activity->available_until?->isPast()) {
            throw ValidationException::withMessages(['activity' => __('This activity is no longer available.')]);
        }

        $answers = $request->validate(['answers' => ['required', 'array']])['answers'];
        $fields = collect(data_get($activity->form?->schema, 'fields', $activity->form?->schema ?? []));
        $errors = [];

        foreach ($fields as $field) {
            if (! is_array($field) || empty($field['required'])) continue;
            $type = strtolower((string) ($field['type'] ?? 'text'));
            if (in_array($type, ['heading', 'divider', 'line_break'], true)) continue;
            $key = (string) ($field['name'] ?? $field['key'] ?? $field['id'] ?? '');
            if ($key !== '' && blank($answers[$key] ?? null)) {
                $errors["answers.$key"] = __('The :attribute field is required.', ['attribute' => $field['label'] ?? $key]);
            }
        }

        if ($errors) throw ValidationException::withMessages($errors);

        $attempt = $activity->submissions()->where('user_id', $request->user()->id)->count() + 1;
        if ($attempt > $activity->max_attempts) {
            throw ValidationException::withMessages(['activity' => __('The attempt limit has been reached.')]);
        }

        $submission = $activity->submissions()->create([
            'user_id' => $request->user()->id,
            'answers' => $answers,
            'attempt' => $attempt,
            'submitted_at' => now(),
        ]);

        return response()->json($submission, 201);
    }
}
