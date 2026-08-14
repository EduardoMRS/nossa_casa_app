<?php

namespace App\Http\Requests\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordingCompletedRequest extends FormRequest
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
            'segment_path' => ['required', 'string', 'max:2048'],
            'segment_duration' => ['nullable', 'string', 'max:100'],
            'worker_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
