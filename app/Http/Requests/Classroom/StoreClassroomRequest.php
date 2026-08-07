<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'gender_restriction' => ['nullable', Rule::in(['male', 'female'])],
            'is_kids' => ['sometimes', 'boolean'],
            'max_members' => ['nullable', 'integer', 'min:1'],
            'teacher_id' => ['nullable', 'string', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['string', 'distinct', 'exists:users,id'],
        ];
    }
}
