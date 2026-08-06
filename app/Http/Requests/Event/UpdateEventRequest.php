<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|Rule>|string>
     */
    public function rules(): array
    {
        $eventId = (string) $this->route('event');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('events', 'slug')->ignore($eventId)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'description' => ['nullable', 'string'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'cover_path' => ['sometimes', 'nullable'],
        ];
    }
}
