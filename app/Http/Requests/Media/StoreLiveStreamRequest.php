<?php

namespace App\Http\Requests\Media;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLiveStreamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (in_array($user?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return true;
        }

        return $user !== null && in_array($user->role, [
            UserRole::MEDIA,
            UserRole::CHURCH_LEADER,
            UserRole::SUPERADMIN,
        ], true) && $user->church()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['sometimes', Rule::in(['publisher', 'pull'])],
            'source_url' => ['nullable', 'required_if:mode,pull', 'string', 'max:2048', 'regex:/\Artsp(s)?:\/\/[^\s]+\z/i'],
            'source_on_demand' => ['sometimes', 'boolean'],
            'record' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'church_id' => [
                Rule::requiredIf(in_array($this->user()?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)),
                'nullable',
                'string',
                'exists:churches,id',
            ],
        ];
    }
}
