<?php

namespace App\Http\Requests\Classroom;

class UpdateClassroomRequest extends StoreClassroomRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
    }
}
