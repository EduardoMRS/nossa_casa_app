<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Models\Church;
use App\Support\ChurchDomainContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChurchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $church = $this->churchForRequest();

        return $church !== null && ($this->user()?->profile?->church_id === $church->id
            || in_array($this->user()?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $church = $this->churchForRequest();
        $mainDomain = app(ChurchDomainContext::class)->mainHost();

        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'not_in:'.$mainDomain,
                'regex:/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/',
                Rule::unique('churches', 'domain')->ignore($church?->id),
                Rule::unique('church_registration_requests', 'domain')->where('status', 'pending'),
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
            'currency' => ['sometimes', Rule::in(['BRL', 'USD', 'EUR', 'GBP', 'ARS', 'PYG', 'BOB', 'CLP', 'COP', 'MXN'])],
            'mail' => ['sometimes', 'array:enabled,allow_branches,host,port,scheme,username,password,from_address,from_name'],
            'mail.enabled' => ['sometimes', 'boolean'],
            'mail.allow_branches' => ['sometimes', 'boolean'],
            'mail.host' => ['nullable', 'required_if:mail.enabled,1', 'string', 'max:255'],
            'mail.port' => ['nullable', 'required_if:mail.enabled,1', 'integer', 'between:1,65535'],
            'mail.scheme' => ['nullable', Rule::in(['tls', 'ssl'])],
            'mail.username' => ['nullable', 'string', 'max:255'],
            'mail.password' => ['nullable', 'string', 'max:1000'],
            'mail.from_address' => ['nullable', 'required_if:mail.enabled,1', 'email', 'max:255'],
            'mail.from_name' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array:instagram,facebook,whatsapp,tiktok,youtube,x,telegram,linkedin'],
            'social_links.*' => ['nullable', 'url:http,https', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'map_embed' => ['nullable', 'string', 'max:3000'],
            'weekly_schedule' => ['nullable', 'array', 'max:30'],
            'weekly_schedule.*.title' => ['required', 'string', 'max:120'],
            'weekly_schedule.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'weekly_schedule.*.start_time' => ['required', 'date_format:H:i'],
            'weekly_schedule.*.end_time' => ['required', 'date_format:H:i', 'after:weekly_schedule.*.start_time'],
            'templates' => ['nullable', 'array:home,posts_index,posts_show,events_index,events_show,form,library,gallery'],
            'templates.*' => ['required', Rule::in(['classic', 'editorial', 'minimal'])],
            'terminology' => ['nullable', 'array:units,roles'],
            'terminology.units' => ['required_with:terminology', 'array:headquarters,branch'],
            'terminology.units.headquarters' => ['required_with:terminology', Rule::in((array) config('terminology.units.headquarters.options'))],
            'terminology.units.branch' => ['required_with:terminology', Rule::in((array) config('terminology.units.branch.options'))],
            'terminology.roles' => ['required_with:terminology', 'array:guest,member,leader,media,church_leader,superadmin,system'],
            'terminology.roles.guest' => ['required_with:terminology', Rule::in((array) config('terminology.roles.guest.options'))],
            'terminology.roles.member' => ['required_with:terminology', Rule::in((array) config('terminology.roles.member.options'))],
            'terminology.roles.leader' => ['required_with:terminology', Rule::in((array) config('terminology.roles.leader.options'))],
            'terminology.roles.media' => ['required_with:terminology', Rule::in((array) config('terminology.roles.media.options'))],
            'terminology.roles.church_leader' => ['required_with:terminology', Rule::in((array) config('terminology.roles.church_leader.options'))],
            'terminology.roles.superadmin' => ['required_with:terminology', Rule::in((array) config('terminology.roles.superadmin.options'))],
            'terminology.roles.system' => ['required_with:terminology', Rule::in((array) config('terminology.roles.system.options'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $context = app(ChurchDomainContext::class);
        $church = $this->churchForRequest();
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

    private function churchForRequest(): ?Church
    {
        $context = app(ChurchDomainContext::class);
        $church = $context->church();

        if ($church || in_array($this->user()?->role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return $church;
        }

        $userChurch = $this->user()?->church;

        return $context->isMainDomain() && blank($userChurch?->domain)
            ? $userChurch
            : null;
    }
}
