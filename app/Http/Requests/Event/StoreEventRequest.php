<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'description' => ['nullable', 'string'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
            'cover_path' => ['nullable'],
            'form_id' => ['nullable', 'string', 'exists:forms,id'],
            'price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'responsible_ids' => ['nullable', 'array'],
            'responsible_ids.*' => ['string', 'distinct', 'exists:users,id'],
            'address' => ['nullable', 'array'],
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
