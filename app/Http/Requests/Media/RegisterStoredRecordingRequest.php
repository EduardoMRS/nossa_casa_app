<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterStoredRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255', 'exists:live_streams,path'],
            'delivery_id' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
            'content_hash' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'duration' => ['nullable', 'string', 'max:100'],
            'worker_id' => ['required', 'string', 'max:255'],
        ];
    }
}
