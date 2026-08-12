<?php

namespace App\Http\Requests\Settings;

use App\Support\ChurchDomainContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChurchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->church !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $church = $this->user()?->church;
        $mainDomain = app(ChurchDomainContext::class)->mainHost();

        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'not_in:'.$mainDomain,
                'regex:/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/',
                Rule::unique('churches', 'domain')->ignore($church?->id),
            ],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'banner_title' => ['nullable', 'string', 'max:180'],
            'banner_subtitle' => ['nullable', 'string', 'max:240'],
            'primary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'accent_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'surface_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'font_family' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['sometimes', 'boolean'],
            'icon_name' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_whatsapp' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'map_embed' => ['nullable', 'string', 'max:3000'],
            'weekly_schedule' => ['nullable', 'array', 'max:30'],
            'weekly_schedule.*.title' => ['required', 'string', 'max:120'],
            'weekly_schedule.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'weekly_schedule.*.start_time' => ['required', 'date_format:H:i'],
            'weekly_schedule.*.end_time' => ['required', 'date_format:H:i', 'after:weekly_schedule.*.start_time'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $church = $this->user()?->church;
        $context = app(ChurchDomainContext::class);
        $currentHost = ChurchDomainContext::normalizeDomain($this->getHost());
        $currentDomain = $church?->domain ?: (
            $currentHost === $context->mainHost() && $church
                ? $church->slug.'.'.$currentHost
                : $currentHost
        );
        $requestedDomain = $this->string('domain')->trim()->toString();

        $this->merge([
            'domain' => ChurchDomainContext::normalizeDomain($requestedDomain ?: $currentDomain),
        ]);
    }
}
