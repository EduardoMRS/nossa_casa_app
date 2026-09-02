<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Church;
use App\Support\ChurchDomainContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChurchNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->actorChurch() !== null
            && in_array($this->user()?->role, [UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM], true);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'parent_church_id' => [
                'required',
                'different:child_church_id',
                Rule::exists((new Church)->getTable(), 'id'),
            ],
            'child_church_id' => [
                'required',
                Rule::exists((new Church)->getTable(), 'id'),
            ],
        ];
    }

    public function actorChurch(): ?Church
    {
        return app(ChurchDomainContext::class)->church() ?? $this->user()?->church;
    }
}
