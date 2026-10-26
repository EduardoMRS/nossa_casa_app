<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Church;
use App\Support\ChurchAccess;
use App\Support\ChurchDomainContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChurchNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ChurchAccess::class)->canManageChurch($this->user(), $this->actorChurch()?->id, [UserRole::CHURCH_LEADER]);
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
