<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MediaStatusHookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255', 'exists:live_streams,path'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'string', 'max:255'],
            'worker_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
