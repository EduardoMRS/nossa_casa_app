<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Church;
use App\Support\ChurchAccess;
use App\Support\ChurchDomainContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveChurchNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ChurchAccess::class)->canManageChurch($this->user(), $this->actorChurch()?->id, [UserRole::CHURCH_LEADER]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'new_parent_church_id' => [
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
