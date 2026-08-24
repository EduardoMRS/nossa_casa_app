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
            'form_id' => ['sometimes', 'nullable', 'string', 'exists:forms,id'],
            'price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'responsible_ids' => ['sometimes', 'nullable', 'array'],
            'responsible_ids.*' => ['string', 'distinct', 'exists:users,id'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.country' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.city' => ['nullable', 'string', 'max:150'],
            'address.neighborhood' => ['nullable', 'string', 'max:150'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:30'],
            'address.complement' => ['nullable', 'string', 'max:150'],
            'address.zipcode' => ['nullable', 'string', 'max:20'],
        ];
    }
}
