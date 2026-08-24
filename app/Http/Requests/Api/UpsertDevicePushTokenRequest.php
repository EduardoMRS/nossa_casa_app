<?php

namespace App\Http\Requests\Api;

use App\Enums\NotificationCategory;
use App\Enums\PushTransport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDevicePushTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'ulid'],
            'transport' => ['required', Rule::enum(PushTransport::class), Rule::notIn([PushTransport::WEB->value])],
            'token' => ['required', 'string', 'max:4096'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', 'string', 'max:20'],
            'enabled_categories' => ['sometimes', 'array', 'max:5'],
            'enabled_categories.*' => ['required', Rule::enum(NotificationCategory::class), 'distinct'],
        ];
    }
}
