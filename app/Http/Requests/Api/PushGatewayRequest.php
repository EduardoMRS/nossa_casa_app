<?php

namespace App\Http\Requests\Api;

use App\Enums\NotificationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PushGatewayRequest extends FormRequest
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
            'device_ids' => ['required', 'array', 'min:1', 'max:100'],
            'device_ids.*' => ['required', 'ulid', 'distinct'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'category' => ['required', Rule::enum(NotificationCategory::class)],
            'deep_link' => ['required', 'string', 'max:500', 'regex:/^(\/|nossacasa:\/\/)/'],
            'data' => ['sometimes', 'array', 'max:10'],
            'data.*' => ['nullable', 'string', 'max:200'],
        ];
    }
}
