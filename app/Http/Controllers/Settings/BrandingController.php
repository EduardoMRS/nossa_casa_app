<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Admin/Branding', [
            'branding' => array_merge($this->defaultBranding(), is_array($branding) ? $branding : []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $church = $request->user()?->church;
        if ($church === null) {
            abort(403);
        }

        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'banner_title' => ['nullable', 'string', 'max:180'],
            'banner_subtitle' => ['nullable', 'string', 'max:240'],
            'primary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'accent_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'surface_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'font_family' => ['nullable', 'string', 'max:120'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'icon_name' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_whatsapp' => ['nullable', 'string', 'max:60'],
            'contact_website' => ['nullable', 'url', 'max:255'],
        ]);

        $setting = Setting::query()->firstOrCreate(
            ['church_id' => $church->id],
            ['options' => []],
        );

        $options = is_array($setting->options) ? $setting->options : [];
        $options['branding'] = array_merge($this->defaultBranding(), $validated);

        $setting->update(['options' => $options]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding updated.')]);

        return back();
    }

    /**
     * @return array<string, string>
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
            'logo_url' => '',
            'icon_name' => 'Sparkles',
            'contact_email' => '',
            'contact_phone' => '',
            'contact_whatsapp' => '',
            'contact_website' => '',
        ];
    }
}
