<?php

namespace App\Support;

use App\Models\Church;

final class MailBrandingResolver
{
    /**
     * @return array{
     *     name: string,
     *     tagline: string,
     *     primary_color: string,
     *     secondary_color: string,
     *     accent_color: string,
     *     logo_url: string,
     *     portal_url: string,
     *     legal_url: string,
     *     is_church: bool
     * }
     */
    public function resolve(?Church $church): array
    {
        $church?->loadMissing('settings');
        $branding = data_get($church?->settings?->options, 'branding', []);
        $branding = is_array($branding) ? $branding : [];
        $portalUrl = $this->portalUrl($church);

        return [
            'name' => $this->brandName($church, $branding),
            'tagline' => is_string($branding['tagline'] ?? null)
                ? trim($branding['tagline'])
                : '',
            'primary_color' => $this->color($branding['primary_color'] ?? null, '#342f87'),
            'secondary_color' => $this->color($branding['secondary_color'] ?? null, '#5f46d8'),
            'accent_color' => $this->color($branding['accent_color'] ?? null, '#c89b2c'),
            'logo_url' => $portalUrl.'/branding/logo',
            'portal_url' => $portalUrl,
            'legal_url' => $portalUrl.'/privacy-and-terms',
            'is_church' => $church !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function brandName(?Church $church, array $branding): string
    {
        $configuredName = $branding['brand_name'] ?? null;

        if (is_string($configuredName) && trim($configuredName) !== '') {
            return trim($configuredName);
        }

        return $church?->name ?? (string) config('app.name');
    }

    private function color(mixed $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[A-Fa-f0-9]{6}$/', $color) === 1
            ? $color
            : $fallback;
    }

    private function portalUrl(?Church $church): string
    {
        $applicationUrl = rtrim((string) config('app.url'), '/');

        if ($church?->domain === null || $church->domain === '') {
            return $applicationUrl;
        }

        $scheme = parse_url($applicationUrl, PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$church->domain;
    }
}
