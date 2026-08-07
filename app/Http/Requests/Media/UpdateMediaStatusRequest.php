<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Rule|string>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MediaStatus::class)],
        ];
    }
}
