<?php

namespace App\Http\Requests\Media;

use App\Enums\MediaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Rule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'gallery' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(MediaStatus::class)],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['string', 'distinct'],
            'file_path' => ['missing'],
            'file' => ['missing'],
            'mimetype' => ['missing'],
            'size' => ['missing'],
        ];
    }
}
