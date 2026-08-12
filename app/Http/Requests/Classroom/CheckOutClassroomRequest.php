<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class CheckOutClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,id'],
            'pin' => ['nullable', 'digits:6'],
            'guardian_user_id' => ['nullable', 'string', 'exists:users,id'],
            'handoff_name' => ['nullable', 'string', 'max:255'],
            'handoff_phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
