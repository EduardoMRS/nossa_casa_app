<?php

namespace App\Http\Requests\Form;

class UpdateFormRequest extends StoreFormRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'schema' => ['sometimes', 'required', 'array:fields'],
            'schema.fields' => ['sometimes', 'required', 'array', 'min:1'],
        ]);
    }
}
