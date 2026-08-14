<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'schema' => ['required', 'array:fields'],
            'schema.fields' => ['required', 'array', 'min:1'],
            'schema.fields.*' => ['array:id,name,label,type,required,options,width,mobile_width,size,height,placeholder,help_text'],
            'schema.fields.*.id' => ['nullable', 'string', 'max:64', 'distinct'],
            'schema.fields.*.name' => ['nullable', 'string', 'alpha_dash:ascii', 'max:64', 'distinct'],
            'schema.fields.*.label' => ['nullable', 'string', 'max:255'],
            'schema.fields.*.type' => ['required', 'in:text,email,textarea,select,radio,checkbox,date,number,heading,divider,line_break'],
            'schema.fields.*.required' => ['nullable', 'boolean'],
            'schema.fields.*.options' => ['nullable', 'array'],
            'schema.fields.*.options.*' => ['string', 'max:255'],
            'schema.fields.*.width' => ['nullable', Rule::in([...range(1, 12), 'full', 'half', 'third'])],
            'schema.fields.*.mobile_width' => ['nullable', Rule::in([...range(1, 12), 'full', 'half', 'third'])],
            'schema.fields.*.size' => ['nullable', 'in:auto,fixed'],
            'schema.fields.*.height' => ['nullable', 'integer', 'min:1', 'max:20'],
            'schema.fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'schema.fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'exists:categories,id'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['string', 'distinct', 'exists:events,id'],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['string', 'distinct', 'exists:posts,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('schema.fields', []) as $index => $field) {
                if (! is_array($field) || in_array($field['type'] ?? null, ['heading', 'divider', 'line_break'], true)) {
                    continue;
                }

                if (blank($field['name'] ?? null) || blank($field['label'] ?? null)) {
                    $validator->errors()->add("schema.fields.{$index}", 'Campos de entrada precisam de identificador e rótulo.');
                }
            }
        }];
    }
}
