<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RelayRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255'],
            'segment_path' => ['required', 'string', 'max:2048'],
            'segment_duration' => ['nullable', 'string', 'max:100'],
            'worker_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
