<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateChurchSettingsRequest;
use App\Models\Setting;
use App\Support\ChurchDomainContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function edit(Request $request): Response
    {
        $church = $request->user()?->church;
        if ($church === null) {
            abort(403);
        }

        $setting = Setting::query()->where('church_id', $church->id)->first();
        $branding = $setting?->options['branding'] ?? [];
        $currentHost = ChurchDomainContext::normalizeDomain($request->getHost());
        $defaultDomain = $church->domain ?: (
            $currentHost === app(ChurchDomainContext::class)->mainHost()
                ? $church->slug.'.'.$currentHost
                : $currentHost
        );
        if (is_array($branding) && ! empty($branding['logo_path'])) {
            $branding['logo_url'] = genUrl($branding['logo_path']);
        }

        return Inertia::render('Admin/Branding', [
            'branding' => array_merge(
                $this->defaultBranding(),
                is_array($branding) ? $branding : [],
                ['domain' => $defaultDomain],
            ),
        ]);
    }

    public function update(UpdateChurchSettingsRequest $request): RedirectResponse
    {
        $church = $request->user()->church;
        $validated = $request->validated();
        $domain = Arr::pull($validated, 'domain');
        $removeLogo = (bool) Arr::pull($validated, 'remove_logo', false);
        Arr::forget($validated, 'logo');
        $validated['map_embed'] = $this->sanitizeMapEmbed($validated['map_embed'] ?? null);
        $validated['weekly_schedule'] = $validated['weekly_schedule'] ?? [];

        $setting = Setting::query()->firstOrCreate(
            ['church_id' => $church->id],
            ['options' => []],
        );

        $options = is_array($setting->options) ? $setting->options : [];
        $currentBranding = array_merge(
            $this->defaultBranding(),
            is_array($options['branding'] ?? null) ? $options['branding'] : [],
        );
        Arr::forget($currentBranding, 'contact_website');

        if ($removeLogo) {
            $this->deleteStoredLogo($currentBranding['logo_path'] ?? null);
            $validated['logo_path'] = '';
            $validated['logo_url'] = '';
        }

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store("church/{$church->id}/branding", 'public');
            $this->deleteStoredLogo($currentBranding['logo_path'] ?? null);
            $validated['logo_path'] = $logoPath;
            $validated['logo_url'] = genUrl($logoPath);
        }

        $options['branding'] = array_merge($currentBranding, $validated);

        $church->update(['domain' => $domain]);
        $setting->update(['options' => $options]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('common.notifications.church_settings_updated')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBranding(): array
    {
        return [
            'brand_name' => config('app.name'),
            'tagline' => '',
            'banner_title' => '',
            'banner_subtitle' => '',
            'primary_color' => '#2f6e79',
            'secondary_color' => '#5f7d95',
            'accent_color' => '#c88b4a',
            'surface_color' => '#f4f7fb',
            'font_family' => 'Manrope, ui-sans-serif',
            'logo_path' => '',
            'logo_url' => '',
            'icon_name' => 'Sparkles',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'address' => '',
            'map_embed' => '',
            'weekly_schedule' => [],
        ];
    }

    private function deleteStoredLogo(mixed $logoPath): void
    {
        if (is_string($logoPath) && $logoPath !== '') {
            Storage::disk('public')->delete($logoPath);
        }
    }

    private function sanitizeMapEmbed(?string $mapEmbed): string
    {
        if (! $mapEmbed) {
            return '';
        }

        $mapUrl = trim($mapEmbed);

        if (str_contains($mapUrl, '<iframe')) {
            preg_match('/\bsrc=["\']([^"\']+)["\']/i', $mapUrl, $matches);
            $mapUrl = html_entity_decode($matches[1] ?? '');
        }

        $host = mb_strtolower((string) parse_url($mapUrl, PHP_URL_HOST));
        $scheme = mb_strtolower((string) parse_url($mapUrl, PHP_URL_SCHEME));
        $allowedHosts = ['google.com', 'googleusercontent.com', 'openstreetmap.org'];
        $isAllowedHost = collect($allowedHosts)->contains(
            fn (string $allowedHost): bool => $host === $allowedHost || str_ends_with($host, '.'.$allowedHost),
        );

        if ($scheme !== 'https' || ! $isAllowedHost) {
            throw ValidationException::withMessages([
                'map_embed' => __('Use a secure Google Maps or OpenStreetMap embed.'),
            ]);
        }

        return $mapUrl;
    }
}
